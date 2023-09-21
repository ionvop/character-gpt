option explicit
dim objShell, objFile, objHttp, objJson
set objShell = CreateObject("wscript.shell")
set objFile = CreateObject("Scripting.FileSystemObject")
set objHttp = CreateObject("MSXML2.XMLHTTP.6.0")
dim directory
directory = objFile.GetParentFolderName(wscript.ScriptFullName)
Include(directory & "\aspJSON.vbs")
set objJson = new aspJSON

sub Main()
    dim settings, chatLog, data
    set settings = new aspJSON
    settings.loadJSON(objFile.OpenTextFile(directory & "\settings.json").ReadAll())

    with settings.Data
        .Add "api-key", objShell.Environment("USER").Item("OPENAI_API_KEY")
    end with

    set chatLog = new aspJSON
    chatLog.loadJSON(objFile.OpenTextFile(directory & "\chat.json").ReadAll())
    set data = Send(settings, chatLog, "What's the random fact of the day?")
    Breakpoint(data.Data("reply"))
end sub

function Send(settings, chatLog, message)
    dim i, key, element, n, temp, temp2, min, data, apiKey, apiUrl, model, headers, curlData, resData, res, result
    n = 0
    set data = new aspJSON
    apiKey = ""
    apiUrl = "https://api.openai.com/v1/chat/completions"
    model = "gpt-3.5-turbo"

    if len(settings.Data("api-key")) > 0 then
        apiKey = settings.Data("api-key")
    end if

    if len(settings.Data("api-url")) > 0 then
        apiUrl = settings.Data("api-url")
    end if

    if len(settings.Data("model")) > 0 then
        model = settings.Data("model")
    end if

    if len(settings.Data("pre-prompt")) > 0 then
        message = settings.Data("pre-prompt") & vbcrlf & vbcrlf & message
    end if

    if len(settings.Data("mid-prompt")) > 0 then
        message = message & vbcrlf & vbcrlf & settings.Data("mid-prompt")
    end if

    with data.Data
        .Add "model", model
        .Add "messages", data.Collection()

        with .Item("messages")
            if settings.Data("system") <> "" then
                .Add n, data.Collection()
    
                with .Item(n)
                    .Add "role", "system"
                    .Add "content", settings.Data("system")
                    n = n + 1
                end with
            end if
    
            if typename(settings.Data("dialogue")) = "Dictionary" then
                for each element in settings.Data("dialogue")
                    set element = settings.Data("dialogue").Item(element)
                    .Add n, element
                    n = n + 1
                next
            end if
    
            temp = array()
    
            for each element in chatLog.Data
                set element = chatLog.Data.Item(element)
                Push temp, element
            next
    
            if settings.Data("memory") = 0 then
                for each element in temp
                    .Add n, element
                    n = n + 1
                next
            else
                min = ubound(temp) - (settings.Data("memory") - 1)
    
                if min < 0 then
                    min = 0
                end if
    
                for i = min to ubound(temp)
                    .Add n, temp(i)
                    n = n + 1
                next
            end if
    
            .Add n, data.Collection()
    
            with .Item(n)
                .Add "role", "user"
                .Add "content", message
                n = n + 1
            end with
        end with
    end with

    set headers = CreateObject("Scripting.Dictionary")
    headers.Add "Content-Type", "application/json"
    headers.Add "Authorization", "Bearer " & apiKey

    resData = Curl(apiUrl, "POST", headers, data.JSONoutput())
    set res = new aspJSON
    res.loadJSON(resData)
    
    with data.Data
        with .Item("messages")
            .Add n, res.Data("choices").Item(0).Item("message")
            n = n + 1

            if len(settings.Data("post-prompt")) > 0 then
                .Add n, data.Collection()

                with .Item(n)
                    .Add "role", "user"
                    .Add "content", settings.Data("post-prompt")
                    n = n + 1
                end with

                resData = Curl(apiUrl, "POST", headers, data.JSONoutput())
                set res = new aspJSON
                res.loadJSON(resData)
                .Add n, res.Data("choices").Item(0).Item("message")
                n = n + 1
            end if
        end with
    end with

    temp = array()
    
    for each element in chatLog.Data
        set element = chatLog.Data.Item(element)
        Push temp, element
    next

    set temp2 = new aspJSON

    with temp2.Data
        .Add "message", temp2.Collection()

        with .Item("message")
            .Add "role", "user"
            .Add "content", message
        end with
    end with

    Push temp, temp2.Data.Item("message")
    Push temp, res.Data("choices").Item(0).Item("message")
    set result = new aspJSON

    with result.Data
        .Add "reply", res.Data("choices").Item(0).Item("message").Item("content")
        .Add "result", result.Collection()

        with .Item("result")
            for i = 0 to ubound(temp)
                .Add i, temp(i)
            next
        end with

        .Add "full-prompt", result.Collection()

        with .Item("full-prompt")
            for each key in data.Data("messages")
                set element = data.Data("messages").Item(key)
                .Add key, element
            next
        end with

        .Add "response", result.Collection()

        with .Item("response")
            for each key in res.Data
                if typename(res.Data.Item(key)) = "Dictionary" then
                    set element = res.Data.Item(key)
                else
                    element = res.Data.Item(key)
                end if

                .Add key, element
            next
        end with
    end with

    set Send = result
end function

function Curl(url, method, headers, data)
    dim element, headerKeys
    objHttp.Open method, url, false
    
    for each element in headers.Keys()
        objHttp.SetRequestHeader element, headers.Item(element)
    next

    objHttp.Send(data)
    Curl = objHttp.ResponseText
end function

function Push(inputArray, pushData)
    dim newData
    redim preserve inputArray(ubound(inputArray) + 1)

    if typename(pushData) = "Dictionary" then
        set newData = CloneDict(pushData)
        set inputArray(ubound(inputArray)) = newData
        Push = inputArray
        exit function
    end if

    inputArray(ubound(inputArray)) = pushData
    Push = inputArray
end function

function CloneDict(data)
    dim element, result
    set result = CreateObject("Scripting.Dictionary")

    for each element in data.Keys()
        result.Add element, data.Item(element)
    next

    set CloneDict = result
end function

sub Include(scriptName)
    ExecuteGlobal objFile.OpenTextFile(scriptName).ReadAll()
End Sub

sub Breakpoint(message)
    if typename(message) = "Variant()" then
        message = join(message, vbcrlf)
    end if

    wscript.Echo(message)
    wscript.Quit()
end sub

Main()