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
        $data = array();

        $settings = array();
            $settings["system"] = "";
            $settings["dialogue"] = array();
            $settings["memory"] = 0;
            $settings["pre-prompt"] = "";
            $settings["mid-prompt"] = "";
            $settings["post-prompt"] = "";

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

        $curlHead = array();
            $curlHead[] = "Content-Type: application/json";
            $curlHead[] = "Authorization: Bearer {$this->apiKey}";

        $curlData = array();
            $curlData["model"] = $this->model;
            $curlData["messages"] = $data;

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

        $result = array();

        foreach ($log as $key => $value) {
            $result[] = $value;
        }

        $result[] = $this->item("user", $message);
        $result[] = $response["choices"][0]["message"];

        $out = array();
            $out["result"] = $result;
            $out["full-prompt"] = $data;
            $out["tokens"] = $response["usage"]["total_tokens"];

        return $out;
    }

    protected function item($role, $content) {
        $result = array();
            $result["role"] = $role;
            $result["content"] = $content;

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
    private $user;
    public $log = "chat.json";

    function __construct($char, $user, $jailbreakMode = 0, $assumeScenario = false) {
        $char = $this->GetCharData($char);

        $settings = array();
            $settings["system"] = file_get_contents("char/system.txt");
            $settings["dialogue"] = array();
            $settings["mid-prompt"] =  "[Try to summarize your response in 2 or below sentences. Do not include your hidden feelings and only show what the user is able to see]";
        
        switch ($jailbreakMode) {
            case 0:
                $settings["dialogue"][] = $this->item("user", file_get_contents("char/jailbreak.txt"));
                break;
            case 1:
                $settings["pre-prompt"] = file_get_contents("char/jailbreak.txt");
                break;
        }

        $settings["dialogue"][] = $this->item("user", "[Your character is {$char["name"]}]");
        $content = $char["description"];
        $content = str_replace("{{user}}", $user, $content);
        $content = str_replace("{{char}}", $char["name"], $content);
        $settings["dialogue"][] = $this->item("user", $content);
            
        if (count($char["dialogue"]) > 0) {
            $settings["dialogue"][] = $this->item("user", "[Begin example dialogue]");

            foreach ($char["dialogue"] as $key => $value) {
                $role = substr($value, 0, strpos($value, ":"));
                $content = substr($value, strpos($value, ":") + 1);
                $content = str_replace("{{user}}", $user, $content);
                $content = str_replace("{{char}}", $char["name"], $content);

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

        if ($assumeScenario) {
            $log = file_get_contents($this->log);
            $log = json_decode($log, true);

            if (count($log) == 0) {
                $content = $char["scenario"];
                $content = str_replace("{{user}}", $user, $content);
                $content = str_replace("{{char}}", $char["name"], $content);
                $log[] = $this->item("assistant", $content);
            }

            file_put_contents($this->log, json_encode($log));
        } else {
            $content = $char["scenario"];
            $content = str_replace("{{user}}", $user, $content);
            $content = str_replace("{{char}}", $char["name"], $content);
            $settings["dialogue"][] = $this->item("assistant", $content);
        }

        $this->settings = $settings;
        $this->user = $user;
        $this->char = $char;
    }

    public function Chat($message) {
        $log = file_get_contents($this->log);
        $log = json_decode($log, true);
        $response = $this->Send($this->settings, $log, $message);

        $logData = array();
            $logData["prompt"] = $response["full-prompt"];
            $logData["tokens"] = $response["tokens"];

        file_put_contents("log/".date("Y-m-d H-i-s").".json", json_encode($logData));
        file_put_contents($this->log, json_encode($response["result"]));
        return $response;
    }

    private function GetCharData($char) {
        $result = array();
            $result["description"] = file_get_contents("char/{$char}/description.txt");
            $result["dialogue"] = array();
            $result["name"] = file_get_contents("char/{$char}/name.txt");
            $result["scenario"] = file_get_contents("char/{$char}/scenario.txt");

        $dialogue = file_get_contents("char/{$char}/dialogue.txt");
        $dialogue = explode("\n", $dialogue);
        $result["dialogue"] = $dialogue;
        return $result;
    }
}

?>