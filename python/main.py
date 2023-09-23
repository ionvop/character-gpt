import sys
import json
import os
import openai
import datetime

class Gpt:
    def __init__(self, apiKey = ""):
        self.apiKey = apiKey
        self.apiBase = "https://api.openai.com/v1"
        self.model = "gpt-3.5-turbo"
        self.log = []
        self.settings = {"system": "",
                         "dialogue": [],
                         "memory": 0,
                         "pre-prompt": "",
                         "mid-prompt": "",
                         "post-prompt": ""}

    def Send(self, message):
        temp = self.settings
        self.settings = {"system": "",
                         "dialogue": [],
                         "memory": 0,
                         "pre-prompt": "",
                         "mid-prompt": "",
                         "post-prompt": ""}
        
        for key in temp:
            self.settings[key] = temp[key]

        data = []

        if self.settings["system"] != "":
            data.append({"role": "system", "content": self.settings["system"]})
        
        for element in self.settings["dialogue"]:
            data.append(element)

        if self.settings["memory"] == 0:
            for element in self.log:
                data.append(element)
        else:
            min = len(self.log) - self.settings["memory"]

            if min < 0:
                min = 0

            for i in range(min, len(self.log)):
                data.append(self.log[i])

        if self.settings["pre-prompt"] != "":
            self.settings["pre-prompt"] += "\n\n"

        if self.settings["mid-prompt"] != "":
            self.settings["mid-prompt"] = "\n\n" + self.settings["mid-prompt"]

        data.append({"role": "user", "content": self.settings["pre-prompt"] + message + self.settings["mid-prompt"]})
        openai.api_key = self.apiKey
        openai.api_base = self.apiBase
        response = openai.ChatCompletion.create(model = self.model, messages = data)
        data.append(response["choices"][0]["message"])

        if self.settings["post-prompt"] != "":
            data.append({"role": "user", "content": self.settings["post-prompt"]})
            response = openai.ChatCompletion.create(model = "gpt-3.5-turbo", messages = data)
            data.append(response["choices"][0]["message"])

        result = {"reply": response["choices"][0]["message"]["content"],
                  "result": [],
                  "full-prompt": data,
                  "response": response}

        for element in self.log:
            result["result"].append(element)

        result["result"].append({"role": "user", "content": message})
        result["result"].append(response["choices"][0]["message"])
        return result
    
class Char(Gpt):
    def __init__(self, apiKey = "", jailbreakMode = 0, includeGreeting = False, charData = {}):
        super().__init__(apiKey)
        self.chatLogPath = ""
        self.dataLogPath = ""
        self.jailbreakMode = 0
        self.includeGreeting = False
        self.char = {"system": "",
                     "jailbreak": "",
                     "name": "",
                     "user": "",
                     "description": "",
                     "dialogue": [],
                     "scenario": ""}

        for key in charData:
            self.char[key] = charData[key]

    def Chat(self, message):
        temp = self.char
        self.char = {"system": "",
                     "jailbreak": "",
                     "name": "",
                     "user": "",
                     "description": "",
                     "dialogue": [],
                     "scenario": ""}
        
        for key in temp:
            self.char[key] = temp[key]

        if self.chatLogPath != "":
            if os.path.exists(self.chatLogPath) == False:
                self.log = []

                if self.includeGreeting:
                    self.log.append({"role": "assistant", "content": self.char["scenario"]})

                open(self.chatLogPath, "w").write(json.dumps(self.log))

            self.log = open(self.chatLogPath).read()
            self.log = json.loads(self.log)

        self.settings["system"] = self.char["system"]
        self.settings["dialogue"] = []
        self.settings["mid-prompt"] = "[Try to summarize your response in 2 or below sentences. Do not include your hidden feelings and only show what the user is able to see]"

        if self.jailbreakMode == 0:
            self.settings["dialogue"].append({"role": "user", "content": self.char["jailbreak"]})
        elif self.jailbreakMode == 1:
            self.settings["pre-prompt"] = self.char["jailbreak"]

        self.settings["dialogue"].append({"role": "user", "content": "[Your character is " + self.char["name"] + " conversing with " + self.char["user"] + "]"})
        content = self.char["description"]
        content = content.replace("{{user}}", self.char["user"])
        content = content.replace("{{char}}", self.char["name"])
        self.settings["dialogue"].append({"role": "user", "content": content})

        if len(self.char["dialogue"]) > 0:
            self.settings["dialogue"].append({"role": "user", "content": "[Begin example dialogue]"})

            for element in self.char["dialogue"]:
                element["content"] = element["content"].replace("{{user}}", self.char["user"])
                element["content"] = element["content"].replace("{{char}}", self.char["name"])
                self.settings["dialogue"].append(element)

            self.settings["dialogue"].append({"role": "user", "content": "[End of example dialogue. Begin roleplay]"})
        else:
            self.settings["dialogue"].append({"role": "user", "content": "[Begin roleplay]"})

        if self.includeGreeting:
            if len(self.log) == 0:
                content = self.char["scenario"]
                content = content.replace("{{user}}", self.char["user"])
                content = content.replace("{{char}}", self.char["name"])
                self.log.append({"role": "assistant", "content": content})
        else:
            content = self.char["scenario"]
            content = content.replace("{{user}}", self.char["user"])
            content = content.replace("{{char}}", self.char["name"])
            self.settings["dialogue"].append({"role": "assistant", "content": content})

        response = self.Send(message)

        if self.chatLogPath != "":
            open(self.chatLogPath, "w").write(json.dumps(response["result"]))

        if self.dataLogPath != "":
            logname = datetime.datetime.now().strftime("%Y-%m-%d %H-%M-%S")
            logname += ".json"
            open(self.dataLogPath + logname, "w").write(json.dumps(response))

        return response
    
    def GetCharFolder(self, char, user):
        result = {"system": open("char/system.txt").read(),
                  "jailbreak": open("char/jailbreak.txt").read(),
                  "name": open("char/" + char + "/name.txt").read(),
                  "user": user,
                  "description": open("char/" + char + "/description.txt").read(),
                  "dialogue": [],
                  "scenario": open("char/" + char + "/scenario.txt").read()}

        dialogue = open("char/" + char + "/dialogue.txt").read()
        dialogue = dialogue.split("\n")

        for element in dialogue:
            role = element[:element.find(":")]
            content = element[element.find(":") + 1:]
            content = content.strip()

            if role == "{{user}}":
                result["dialogue"].append({"role": "user", "content": content})
            elif role == "{{char}}":
                result["dialogue"].append({"role": "assistant", "content": content})

        self.char = result

def main():
    if len(sys.argv) == 1:
        print("wip documentations...")
        exit()

    if "-char" in sys.argv:
        charname = GetArgsValue(sys.argv, "-char")
        user = "the user"
        chatLogPath = ""
        dataLogPath = ""
        message = ""

        if "-u" in sys.argv:
            user = GetArgsValue(sys.argv, "-u")

        if "-clog" in sys.argv:
            chatLogPath = GetArgsValue(sys.argv, "-clog")

        if "-dlog" in sys.argv:
            dataLogPath = GetArgsValue(sys.argv, "-dlog")

        char = Char(os.getenv("OPENAI_API_KEY"))
        char.GetCharFolder(charname, user)
        char.chatLogPath = chatLogPath
        char.dataLogPath = dataLogPath

        if "-m" in sys.argv:
            message = GetArgsValue(sys.argv, "-m")

        response = char.Chat(message)
        print(response["reply"])
    else:
        gpt = Gpt(os.getenv("OPENAI_API_KEY"))
        message = ""

        if "-s" in sys.argv:
            temp = GetArgsValue(sys.argv, "-s")

            if temp[:1] == "@":
                temp = open(temp[1:]).read()
            
            gpt.settings = json.loads(temp)

        if "-l" in sys.argv:
            temp = GetArgsValue(sys.argv, "-l")

            if temp[:1] == "@":
                temp = open(temp[1:]).read()
            
            gpt.log = json.loads(temp)

        if "-m" in sys.argv:
            temp = GetArgsValue(sys.argv, "-m")

            if temp[:1] == "@":
                temp = open(temp[1:]).read()
            
            message = temp

        if "-uwu" in sys.argv:
            gpt.settings["post-prompt"] = "[Say that again but this time try to uwu-fy most of the words in your response. Don't remark on this command]"

        response = gpt.Send(message)

        if "-o" in sys.argv:
            open(GetArgsValue(sys.argv, "-o"), "w").write(response["result"])
        
        if "-r" in sys.argv:
            print(response["reply"])
        elif "-v" in sys.argv:
            print(json.dumps(response))
        else:
            print(json.dumps(response["result"]))

def GetArgsValue(args, option):
    for i in range(0, len(args)):
        if args[i] == option:
            if i + 1 >= len(args):
                return None

            return args[i + 1]

    return None

main()