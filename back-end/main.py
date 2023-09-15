import sys
import json
import os
import openai
import datetime

directory = sys.argv[0]
directory = directory[:directory.rfind("/") + 1]

def main():
    settings = {"system": "", "dialogue": [], "memory": 0, "pre-prompt": "", "mid-prompt": "", "post-prompt": ""}
    log = []
    message = ""

    if len(sys.argv) <= 1:
        print("wip documentations...")
        return
    
    if "-s" in sys.argv:
        settings["system"] = getArgsValue(sys.argv, "-s")
    
    if "-l" in sys.argv:
        temp = getArgsValue(sys.argv, "-l")
        temp = open(temp).read()
        log = json.loads(temp)

    if "-m" in sys.argv:
        message = getArgsValue(sys.argv, "-m")

        if message[:1] == "@":
            message = open(message[1:]).read()

    if "-uwu" in sys.argv:
        settings["dialogue"].append({"role": "user", "content": open("jailbreak__uwu--modified.txt").read()})
        settings["dialogue"].append({"role": "assistant", "content": "UWU Mode enabled"})
        settings["dialogue"].append({"role": "user", "content": "Hello"})
        settings["dialogue"].append({"role": "assistant", "content": "Hewwo! How can dis cute wittle AI hewp chu today? OwO"})
        settings["pre-prompt"] = "Make sure that most of the words in your response is uwu-fied, and don't remark on this command."

    if "-uwu2" in sys.argv:
        settings["post-prompt"] = "Say that again but this time uwu-fy most of the words in your response. Don't remark on this command"

    if "-char" in sys.argv:
        char = getArgsValue(sys.argv, "-char")
        user = "the user"

        if "-user" in sys.argv:
            user = getArgsValue(sys.argv, "-user")

        char = getCharData(char, user)
        settings["system"] = open("char/system.txt").read()
        settings["dialogue"].append({"role": "user", "content": open("char/jailbreak.txt").read()})
        settings["dialogue"].append({"role": "user", "content": "[Your character is " + char["name"] + "]"})
        settings["dialogue"].append({"role": "user", "content": char["description"]})

        if len(char["dialogue"]) > 0:
            settings["dialogue"].append({"role": "user", "content": "[Begin example dialogue]"})
            settings["dialogue"].append({"role": "assistant", "content": char["greeting"]})

            for element in char["dialogue"]:
                if element[:element.find(":")] == "{{user}}":
                    settings["dialogue"].append({"role": "user", "content": element[element.find(":") + 1:]})
                elif element[:element.find(":")] == "{{char}}":
                    settings["dialogue"].append({"role": "assistant", "content": element[element.find(":") + 1:]})

            settings["dialogue"].append({"role": "user", "content": "[End of example dialogue. Begin roleplay]"})
        else:
            settings["dialogue"].append({"role": "user", "content": "[Begin roleplay]"})

        settings["dialogue"].append({"role": "assistant", "content": char["greeting"]})
        settings["mid-prompt"] = "[Try to summarize your response in 2 or below sentences. Do not include your hidden feelings and only show what the user is able to see.]"

    result = gpt(settings, log, message)
    resultJson = json.dumps(result)

    if "-o" in sys.argv:
        output = getArgsValue(sys.argv, "-o")
        open(output, "w").write(resultJson)

    if "-r" in sys.argv:
        print(result[len(result) - 1]["content"])
        return
    
    print(resultJson)
    return

def getCharData(folder, user):
    result = {"name": "", "description": "", "greeting": "", "dialogue": []}
    result["name"] = result["description"] = open("char/" + folder + "/name.txt").read()
    result["description"] = open("char/" + folder + "/description.txt").read()
    result["description"] = result["description"].replace("{{char}}", result["name"])
    result["description"] = result["description"].replace("{{user}}", user)
    result["greeting"] = open("char/" + folder + "/greeting.txt").read()
    result["greeting"] = result["greeting"].replace("{{char}}", result["name"])
    result["greeting"] = result["greeting"].replace("{{user}}", user)
    temp = open("char/" + folder + "/dialogue.txt").read()
    temp = temp.split("\n")

    for element in temp:
        if element[:element.find(":")] == "{{user}}":
            element = element[:element.find(":")] + element[element.find(":"):].replace("{{char}}", result["name"])
            element = element[:element.find(":")] + element[element.find(":"):].replace("{{user}}", user)

        result["dialogue"].append(element)

    return result

def getArgsValue(args, option):
    for i in range(0, len(args)):
        if args[i] == option:
            if i + 1 >= len(args):
                return None

            return args[i + 1]

    return None

# settings: {system: "", dialogue: [], pre-prompt: "", post-prompt: ""}
# log: [{role: "", content: ""}]
# message: ""
# return: [{role: "", content: ""}]
def gpt(settings, log, message):
    openai.api_key = os.getenv("OPENAI_API_KEY")
    data = []

    if settings["system"] != "":
        data.append({"role": "system", "content": settings["system"]})

    for element in settings["dialogue"]:
        data.append(element)

    if settings["pre-prompt"] != "":
        settings["pre-prompt"] += "\n\n"

    if settings["mid-prompt"] != "":
        settings["mid-prompt"] = "\n\n" + settings["mid-prompt"]

    if settings["memory"] == 0:
        for element in log:
            data.append(element)
    else:
        min = len(log) - settings["memory"]

        if min < 0:
            min = 0

        for i in range(min, len(log)):
            data.append(log[i])

    data.append({"role": "user", "content": settings["pre-prompt"] + message + settings["mid-prompt"]})
    chat_completion = openai.ChatCompletion.create(model = "gpt-3.5-turbo", messages = data)
    data.append(chat_completion["choices"][0]["message"])

    if settings["post-prompt"] != "":
        data.append({"role": "user", "content": settings["post-prompt"]})
        chat_completion = openai.ChatCompletion.create(model = "gpt-3.5-turbo", messages = data)
        data.append(chat_completion["choices"][0]["message"])

    result = []

    for element in log:
        result.append(element)

    result.append({"role": "user", "content": message})
    result.append(chat_completion["choices"][0]["message"])
    logname = datetime.datetime.now().strftime("%Y-%m-%d %H-%M-%S")
    logname += ".json"
    open("log/" + logname, "w").write(json.dumps(data))
    return result

def breakpoint(message):
    print(message)
    exit(0)

main()