"use strict";

class Gpt {
    constructor(apiKey = "") {
        this.apiKey = "";
        this.apiUrl = "https://api.openai.com/v1/chat/completions";
        this.model = "gpt-3.5-turbo";
        this.log = [];
        
        this.settings = {
            system: "",
            dialogue: [],
            memory: 0,
            prePrompt: "",
            midPrompt: "",
            postPrompt: ""
        }
    }

    Send(message, callback) {
        let data = [];

        if (this.settings.system != "") {
            data.push({
                role: "system",
                content: this.settings.system
            });
        }

        for (let element of this.settings.dialogue) {
            data.push(element);
        }

        if (this.settings.memory == 0) {
            for (let element of this.log) {
                data.push(element);
            }
        } else {
            let min = this.log.length - this.settings.memory;

            if (min < 0) {
                min = 0;
            }

            for (let i = 0; i < this.log.length; i++) {
                data.push(this.log[i]);
            }
        }

        if (this.settings.prePrompt != "") {
            this.settings.prePrompt += "\n\n";
        }

        if (this.settings.midPrompt != "") {
            this.settings.midPrompt = "\n\n" + this.settings.midPrompt;
        }

        data.push({
            role: "user",
            content: this.settings.prePrompt + message + this.settings.midPrompt
        });

        fetch(this.apiUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + settings.apiKey
            },
            body: JSON.stringify({
                model: this.model,
                messages: data
            })
        }).then((res) => {res.text}).then((text) => {
            let res = JSON.parse(text);
            data.push(res.choices[0].message);

            if (this.settings.postPrompt != "") {
                data.push({
                    role: "user",
                    content: this.settings.postPrompt
                });

                fetch(this.apiUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": "Bearer " + settings.apiKey
                    },
                    body: JSON.stringify({
                        model: this.model,
                        messages: data
                    })
                }).then((res) => {res.text}).then((text) => {
                    let res = JSON.parse(text);
                    data.push(res.choices[0].message);

                    let result = {
                        reply: res.choices[0].message.content,
                        result: [],
                        fullPrompt: data,
                        response: res
                    };

                    for (let element of this.log) {
                        result.result.push(element);
                    }

                    result.result.push({
                        role: "user",
                        content: message
                    });

                    result.result.push(res.choices[0].message)
                    callback(result);
                });

                return;
            }

            let result = {
                reply: res.choices[0].message.content,
                result: [],
                fullPrompt: data,
                response: res
            };

            for (let element of this.log) {
                result.result.push(element);
            }

            result.result.push({
                role: "user",
                content: message
            });

            result.result.push(res.choices[0].message)
            callback(result);
        });
    }
}