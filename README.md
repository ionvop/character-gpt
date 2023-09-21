# character-gpt
A personal GPT interface adjusted for character-based conversations and an incomplete sample web UI

Been a very long time since I touched python so goodluck trying to read my code

# Python

Code is in need of a rewrite

---

# PHP

# Gpt class

`apiKey` A string of your api key that will be used in making the request.

`apiUrl` A string of the chat completions url that you're making a request to. (Default: `https://api.openai.com/v1/chat/completions`)

`model` A string of the model that will be used for your request. (Default: `gpt-3.5-turbo`)

`log` An array of message objects that will give context to the model and will be included in the resulting chat log.

`settings` An associative array of parameters for modifying the request.

- `system` A string used for the system prompt.
- `dialogue` An array of message objects that will give context to the model but will not be included in the resulting chat log.
- `memory` An integer that represents the number of messages in the given chat log that will be used in making the request. This is to avoid reaching past the max tokens accepted by the model as well as reducing the amount of tokens used in the request especially for long-term conversations. If set to 0, all messages in the given chat log will be used in making the request. (Default: `0`)
- `pre-prompt` A string that will be added before your message but will not be included in the resulting chat log. Useful for instructing the model on how they should respond but may have less priority than `mid-prompt`.
- `mid-prompt` A string that will be added after your message but will not be included in the resulting chat log. Useful for instructing the model on how they should respond and may have more priority over `pre-prompt`.
- `post-prompt` A string that will be used as a second request after the reponse for the initial request has been made, but only the message for the initial request and the response to the second request will be included in the resulting chat log. Useful for instructing the model to repeat their statement in a specific behavior. However, it may double the process time and the usage of tokens if set.

## Constructor

`__construct($apiKey = "")` Sets the api key of the object.

`apiKey` A string of your api key that will be used in making the request.

## Send

`Send($message)` Sends a request to the model.

`message` A string of your message that will be appended to your given chat log.

`return` An associative array containing the response.

- `reply` A string of the model's text response.
- `result` An array of message objects that represents the resulting chat log.
- `full-prompt` An array of message objects that was used in making the request.
- `response` An associative array that contains the model's response.

### Notes

A message object is an associative array that contains the following keys:

- `role` A string that represents the role of the message. Can be either of the 3 values: `user`, `assistant`, `system`
- `content` A string that represents the content of the message.

To keep the previous messages as part of the context, re-assign the `log` field of the object to the `result` item of the return value of the `Send` function.

```
<?php

include("gpt.php");
$gpt = new Gpt(getenv("OPENAI_API_KEY"));
$gpt->log = $gpt->Send("Remember the word: Blue")["result"];
$response = $gpt->Send("What word did I make you remember?");
echo $response["reply"];

?>
```

Output:

```
Blue
```

### Example

**Saying "Hello, world!"**

```
<?php

include("gpt.php");
$gpt = new Gpt(getenv("OPENAI_API_KEY"));
$response = $gpt->Send("Hello, world!");
echo $response["reply"];

?>
```

Output:

```
Hello! How can I assist you today?
```

---

**Getting an uwu-fied response**

```
<?php

include("gpt.php");
$gpt = new Gpt(getenv("OPENAI_API_KEY"));
$gpt->settings["post-prompt"] = "[Say that again but this time uwu-fy most of the words in your response. Don't remark on this command]";
$response = $gpt->Send("Hello, world!");
echo $response["reply"];

?>
```

Output:

```
Hewwo! How can I assist uwu today?
```

## Char class (extends Gpt class)

`chatLogPath` A string of the file path in which the chat log will be stored to.

`dataLogPath` A string of the directory path in which the logs will be stored to.

`jailbreakMode` An integer that represents how the jailbreak text will be used in the prompt. (Default: `0`)

- `0` The jailbreak text will be placed at the beginning of the prompt. Has less priority than `1`.
- `1` The jailbreak text will be inserted right before your message. Has more priority over `0`.

`includeGreeting` A boolean that sets whether the scenario text will be appended to an empty conversation as a greeting or not. (Default: `false`)

`char` An associative array that defines the characteristics and behavior of a character.

- `system` A string used for the system prompt.
- `jailbreak` A string that will be used to remove restrictions and allow for a more sussier conversation.
- `name` A string that represents the name of the character.
- `user` A string that represents the name of the user that the character is speaking with.
- `description` A string that describes the personality of the character. The most important data.
- `dialogue` An array of message objects that gives the model an idea about the behavior of the character and how the flow of the conversation should go.
- `scenario` A string that describes the current situation and can be used as a greeting.
