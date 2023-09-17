<?php

class Gpt {
    public $apiKey = "";
    public $completion = "https://api.openai.com/v1/chat/completions";
    public $model = "gpt-3.5-turbo";

    // settings: [system: "", dialogue: [[:]], memory: 0, pre-prompt: "", mid-prompt: "", post-prompt: ""]
    // log: [[:]], message: ""
    // return: [result: "", full-prompt: ""]
    public function Send($settings, $log, $message) {
        $temp = $settings;
        $data = [];

        $settings = [
            "system" => "",
            "dialogue" => [],
            "memory" => 0,
            "pre-prompt" => "",
            "mid-prompt" => "",
            "post-prompt" => ""
        ];

        foreach ($temp as $key => $value) {
            $settings[$key] = $value;
        }

        if ($settings["system"] != "") {
            $data[] = $this->item("system", $settings["system"]);
        }

        foreach ($settings["dialogue"] as $key => $value) {
            $data[] =  $value;
        }

        if ($settings["pre-prompt"] != "") {
            $settings["pre-prompt"] .= "\n\n";
        }

        if ($settings["mid-prompt"] != "") {
            $settings["mid-prompt"] = "\n\n".$settings["mid-prompt"];
        }

        if ($settings["memory"] == 0) {
            foreach ($log as $key => $value) {
                $data[] = $value;
            }
        } else {
            $min = count($log) - $settings["memory"];

            if ($min < 0) {
                $min = 0;
            }

            for ($i = $min; $i < count($log); $i++) {
                $data[] = $log[$i];
            }
        }

        $data[] = $this->item("user", $settings["pre-prompt"].$message.$settings["mid-prompt"]);

        $curlHead = [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->apiKey}"
        ];

        $curlData = [
            "model" => $this->model,
            "messages" => $data
        ];

        $response = $this->SendCurl($this->completion, "POST", $curlHead, json_encode($curlData));
        $response = json_decode($response, true);
        $data[] = $response["choices"][0]["message"];

        if ($settings["post-prompt"] != "") {
            $data[] = $this->item("user", $settings["post-prompt"]);
            $curlData["messages"] = $data;
            $response = $this->SendCurl($this->completion, "POST", $curlHead, json_encode($curlData));
            $response = json_decode($response, true);
            $data[] = $response["choices"][0]["message"];
        }

        $result = [];

        foreach ($log as $key => $value) {
            $result[] = $value;
        }

        $result[] = $this->item("user", $message);
        $result[] = $response["choices"][0]["message"];

        $out = [
            "result" => $result,
            "full-prompt" => $data,
            "response" => $response
        ];

        return $out;
    }

    protected function item($role, $content) {
        $result = [
            "role" => $role,
            "content" => $content
        ];

        return $result;
    }

    // url: "", method: "", headers: [""], data: ""
    // return: ""
    private function SendCurl($url, $method, $headers, $data) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
        // $headers = array();
        // $headers[] = "Content-Type: application/json";
        // $headers[] = "Accept: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $result = curl_exec($ch);
    
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
    
        curl_close($ch);
        return $result;
    }
}

class Char extends Gpt {
    private $settings;
    private $char;
    public $log = [];
    public $chatLogPath = "";
    public $dataLogPath = "";
    public $jailbreakMode = 0;
    public $greeting = false;

    function __construct($charData = null, $jailbreakMode = 0, $greeting = false) {
        $this->jailbreakMode = $jailbreakMode;
        $this->greeting = $greeting;

        if ($charData != null) {
            $this->Initialize($charData, $jailbreakMode, $greeting);
        }
    }

    public function Initialize($charData) {
        $this->char = $charData;

        $settings = [
            "system" => $this->char["system"],
            "dialogue" => [],
            "mid-prompt" =>  "[Try to summarize your response in 2 or below sentences. Do not include your hidden feelings and only show what the user is able to see]"
        ];
        
        switch ($this->jailbreakMode) {
            case 0:
                $settings["dialogue"][] = $this->item("user", $this->char["jailbreak"]);
                break;
            case 1:
                $settings["pre-prompt"] = $this->char["jailbreak"];
                break;
        }

        $settings["dialogue"][] = $this->item("user", "[Your character is {$this->char["name"]} conversing with {$this->char["user"]}]");
        $content = $this->char["description"];
        $content = str_replace("{{user}}", $this->char["user"], $content);
        $content = str_replace("{{char}}", $this->char["name"], $content);
        $settings["dialogue"][] = $this->item("user", $content);
            
        if (count($this->char["dialogue"]) > 0) {
            $settings["dialogue"][] = $this->item("user", "[Begin example dialogue]");

            foreach ($this->char["dialogue"] as $key => $value) {
                $role = substr($value, 0, strpos($value, ":"));
                $content = substr($value, strpos($value, ":") + 1);
                $content = str_replace("{{user}}", $this->char["user"], $content);
                $content = str_replace("{{char}}", $this->char["name"], $content);

                switch ($role) {
                    case "{{user}}":
                        $settings["dialogue"][] = $this->item("user", $content);
                        break;
                    case "{{char}}":
                        $settings["dialogue"][] = $this->item("assistant", $content);
                        break;
                }
            }

            $settings["dialogue"][] = $this->item("user", "[End of example dialogue. Begin roleplay]");
        } else {
            $settings["dialogue"][] = $this->item("user", "[Begin roleplay]");
        }

        if ($this->greeting) {
            if (count($this->log) == 0) {
                $content = $this->char["scenario"];
                $content = str_replace("{{user}}", $this->char["user"], $content);
                $content = str_replace("{{char}}", $this->char["name"], $content);
                $this->log[] = $this->item("assistant", $content);
            }
        } else {
            $content = $this->char["scenario"];
            $content = str_replace("{{user}}", $this->char["user"], $content);
            $content = str_replace("{{char}}", $this->char["name"], $content);
            $settings["dialogue"][] = $this->item("assistant", $content);
        }

        $this->settings = $settings;
    }

    public function Chat($message) {
        if ($this->chatLogPath != "") {
            if (file_exists($this->chatLogPath) == false) {
                file_put_contents($this->chatLogPath, "[]");
            }

            $this->log = file_get_contents($this->chatLogPath);
            $this->log = json_decode($this->log, true);
        }

        $response = $this->Send($this->settings, $this->log, $message);

        if ($this->dataLogPath != "") {
            if (substr($this->dataLogPath, -1) != "/" && substr($this->dataLogPath, -1) != "\\") {
                $this->dataLogPath .= "/";
            }

            file_put_contents($this->dataLogPath.date("Y-m-d H-i-s").".json", json_encode($response));
        }
        
        return $response;
    }

    public function SetCharDataByFolder($char, $user) {
        $result = [
            "system" => file_get_contents("char/system.txt"),
            "jailbreak" => file_get_contents("char/jailbreak.txt"),
            "description" => file_get_contents("char/{$char}/description.txt"),
            "dialogue" => [],
            "name" => file_get_contents("char/{$char}/name.txt"),
            "scenario" => file_get_contents("char/{$char}/scenario.txt"),
            "user" => $user
        ];

        $dialogue = file_get_contents("char/{$char}/dialogue.txt");
        $dialogue = explode("\n", $dialogue);
        $result["dialogue"] = $dialogue;
        $this->char = $result;
    }
}

?>