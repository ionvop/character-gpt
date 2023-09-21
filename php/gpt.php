<?php

class Gpt {
    public $apiKey = "";
    public $apiUrl = "https://api.openai.com/v1/chat/completions";
    public $model = "gpt-3.5-turbo";
    public $log = [];

    public $settings = [
        "system" => "",
        "dialogue" => [],
        "memory" => 0,
        "pre-prompt" => "",
        "mid-prompt" => "",
        "post-prompt" => ""
    ];

    function __construct($apiKey = "") {
        $this->apiKey = $apiKey;
    }

    public function Send($message) {
        $data = [];

        if ($this->settings["system"] != "") {
            $data[] = $this->item("system", $this->settings["system"]);
        }

        foreach ($this->settings["dialogue"] as $key => $value) {
            $data[] =  $value;
        }

        if ($this->settings["pre-prompt"] != "") {
            $this->settings["pre-prompt"] .= "\n\n";
        }

        if ($this->settings["mid-prompt"] != "") {
            $this->settings["mid-prompt"] = "\n\n".$this->settings["mid-prompt"];
        }

        if ($this->settings["memory"] == 0) {
            foreach ($this->log as $key => $value) {
                $data[] = $value;
            }
        } else {
            $min = count($this->log) - $this->settings["memory"];

            if ($min < 0) {
                $min = 0;
            }

            for ($i = $min; $i < count($this->log); $i++) {
                $data[] = $this->log[$i];
            }
        }

        $data[] = $this->item("user", $this->settings["pre-prompt"].$message.$this->settings["mid-prompt"]);

        $curlHead = [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->apiKey}"
        ];

        $curlData = [
            "model" => $this->model,
            "messages" => $data
        ];

        $response = $this->SendCurl($this->apiUrl, "POST", $curlHead, json_encode($curlData));
        $response = json_decode($response, true);
        $data[] = $response["choices"][0]["message"];

        if ($this->settings["post-prompt"] != "") {
            $data[] = $this->item("user", $this->settings["post-prompt"]);
            $curlData["messages"] = $data;
            $response = $this->SendCurl($this->apiUrl, "POST", $curlHead, json_encode($curlData));
            $response = json_decode($response, true);
            $data[] = $response["choices"][0]["message"];
        }

        $result = [
            "reply" => $response["choices"][0]["message"]["content"],
            "result" => [],
            "full-prompt" => $data,
            "response" => $response
        ];

        foreach ($this->log as $key => $value) {
            $result["result"][] = $value;
        }

        $result["result"][] = $this->item("user", $message);
        $result["result"][] = $response["choices"][0]["message"];
        return $result;
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
    public $chatLogPath = "";
    public $dataLogPath = "";
    public $jailbreakMode = 0;
    public $includeGreeting = false;

    public $char = [
        "system" => "",
        "jailbreak" => "",
        "name" => "",
        "user" => "",
        "description" => "",
        "dialogue" => [],
        "scenario" => ""
    ];

    function __construct($apiKey = "", $jailbreakMode = 0, $includeGreeting = false, $charData = null) {
        $this->apiKey = $apiKey;
        $this->jailbreakMode = $jailbreakMode;
        $this->includeGreeting = $includeGreeting;

        if ($charData != null) {
            $this->char = $charData;
        }
    }

    public function Chat($message) {
        if ($this->chatLogPath != "") {
            if (file_exists($this->chatLogPath) == false) {
                $this->log = [];

                if ($this->includeGreeting) {
                    $this->log[] = $this->item("assistant", $this->char["scenario"]);
                }

                file_put_contents($this->chatLogPath, json_encode($this->log));
            }

            $this->log = file_get_contents($this->chatLogPath);
            $this->log = json_decode($this->log, true);
        }
        
        switch ($this->jailbreakMode) {
            case 0:
                $this->settings["dialogue"][] = $this->item("user", $this->char["jailbreak"]);
                break;
            case 1:
                $this->settings["pre-prompt"] = $this->char["jailbreak"];
                break;
        }

        $this->settings["dialogue"][] = $this->item("user", "[Your character is {$this->char["name"]} conversing with {$this->char["user"]}]");
        $content = $this->char["description"];
        $content = str_replace("{{user}}", $this->char["user"], $content);
        $content = str_replace("{{char}}", $this->char["name"], $content);
        $this->settings["dialogue"][] = $this->item("user", $content);
            
        if (count($this->char["dialogue"]) > 0) {
            $this->settings["dialogue"][] = $this->item("user", "[Begin example dialogue]");

            foreach ($this->char["dialogue"] as $key => $value) {
                $value["content"] = str_replace("{{user}}", $this->char["user"], $value["content"]);
                $value["content"] = str_replace("{{char}}", $this->char["name"], $value["content"]);
                $this->settings["dialogue"][] = $this->item("user", $content);
            }

            $this->settings["dialogue"][] = $this->item("user", "[End of example dialogue. Begin roleplay]");
        } else {
            $this->settings["dialogue"][] = $this->item("user", "[Begin roleplay]");
        }

        if ($this->includeGreeting) {
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
            $this->settings["dialogue"][] = $this->item("assistant", $content);
        }

        $response = $this->Send($message);

        if ($this->dataLogPath != "") {
            if (substr($this->dataLogPath, -1) != "/" && substr($this->dataLogPath, -1) != "\\") {
                $this->dataLogPath .= "/";
            }

            file_put_contents($this->dataLogPath.date("Y-m-d H-i-s").".json", json_encode($response));
        }

        if ($this->chatLogPath != "") {
            file_put_contents($this->chatLogPath, json_encode($response["result"]));
        }
        
        return $response;
    }

    public function GetCharFolder($char, $user) {
        $result = [
            "system" => file_get_contents("char/system.txt"),
            "jailbreak" => file_get_contents("char/jailbreak.txt"),
            "name" => file_get_contents("char/{$char}/name.txt"),
            "user" => $user,
            "description" => file_get_contents("char/{$char}/description.txt"),
            "dialogue" => [],
            "scenario" => file_get_contents("char/{$char}/scenario.txt")
        ];

        $dialogue = file_get_contents("char/{$char}/dialogue.txt");
        $dialogue = explode("\n", $dialogue);

        foreach ($dialogue as $key => $value) {
            $role = substr($value, 0, strpos($value, ":"));
            $role = trim($role);
            $content = substr($value, strpos($value, ":") + 1);
            $content = trim($content);

            switch ($role) {
                case "{{user}}":
                    $result["dialogue"][] = $this->item("user", $content);
                    break;
                case "{{char}}":
                    $result["dialogue"][] = $this->item("assistant", $content);
                    break;
            }
        }

        $result["dialogue"] = $dialogue;
        $this->char = $result;
    }
}

?>
