# character-gpt
A personal GPT interface adjusted for character-based conversations and an incomplete sample web UI

Been a very long time since I touched python so goodluck trying to read my code

# Python

Code is in need of a rewrite

# PHP

## Gpt class

`apiKey` A string of your api key that will be used in making the requests.

`baseUrl` A string of the chat completions url that you're making a request to. (Default: `https://api.openai.com/v1/chat/completions`)

`model` A string of the model that will be used for your request. (Default: `gpt-3.5-turbo`)

### Send

`Send($settings, $log, $message)` Sends a request to the model.

`settings` An associative array of parameters for modifying the request.

- `system` A string used for the system prompt.
- `dialogue` An array of message objects that will give context to the model but will not be included in the resulting chat log.
- `memory` An integer that represents the number of messages in the given chat log that will be used in making the request. This is to avoid reaching past the max tokens accepted by the model as well as reducing the amount of tokens used in the request especially for long-term conversations. If set to 0, all messages in the given chat log will be used in making the request. (Default: `0`)
- `pre-prompt` A string that will be added before your message but will not be included in the resulting chat log. Useful for instructing the model on how they should respond but may have less priority than `mid-prompt`.
- `mid-prompt` A string that will be added after your message but will not be included in the resulting chat log. Useful for instructing the model on how they should respond and may have more priority over `pre-prompt`.
- `post-prompt` A string that will be used as a second request after the reponse for the initial request has been made, but only the message for the initial request and the response to the second request will be included in the resulting chat log. Useful for instructing the model to repeat their statement in a specific behavior. However, it may double the usage of tokens if set.

`log` An array of message objects that will give context to the model and will be included in the resulting chat log.

`message` A string of your message that will be appended to your given chat log.

`return` An associative array containing the response.

- `result` An array of message objects that represents the resulting chat log.
- `reply` A string of the model's text response.
- `full-prompt` An array of message objects that was used in making the request.
- `response` An associative array that contains the model's response.

#### Notes

A message object is an associative array that contains the following keys:

- `role` A string that represents the role of the message. Can be either of the 3 values: `user`, `assistant`, `system`
- `content` A string that represents the content of the message.
