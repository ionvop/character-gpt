<?php

include("common.php");

$message = null;

if (isset($_POST["reply"])) {
    file_put_contents("message.txt", $_POST["reply"]);
    $command = "python \"{$Gpt}\" -char ero -l chat.json -m @message.txt -o chat.json";
    $response = `{$command}`;
}

$chats = GetChats();

?>

<html>
    <head>
        <title>
            Chatting with Ero-chan
        </title>
        <base href="./">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
        <link rel="stylesheet" href="style.css">
        <style>
            .container {
                display: grid;
                grid-template-columns: 1fr 2fr 1fr;
            }

            .chat {
                display: grid;
                height: 99vh;
                grid-template-columns: 1fr;
                grid-template-rows: 1fr max-content;
            }

            .chat__render {
                overflow-y: auto;
            }

            .chat__item__container {
                display: grid;
                grid-template-columns: max-content 1fr;
            }

            .chat__item__avatar {
                padding: 1rem;
            }

            .chat__item__avatar > img {
                width: 5rem;
                height: 5rem;
                border-radius: 2.5rem;
            }

            .chat__item__panel {
                padding: 1rem;
            }

            .chat__item__panel__message {
                margin-top: 1rem;
            }

            .chat__input__container {
                display: grid;
                grid-template-columns: 1fr max-content;
            }

            .chat__input__text {
                padding: 1rem;
            }

            .chat__input__text .-textarea {
                max-height: 50vh;
                width: 100%;
                resize: none;
            }

            .chat__input__send {
                padding: 1rem;
            }

            .chat__input__send span {
                font-size: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="main">
            <div class="container">
                <div>

                </div>
                <div class="chat">
                    <div class="chat__render">
                        <?php
                            $chat = new stdClass();
                                $chat->avatar = "assets/default.jpg";
                                $chat->name = "Ero-chan";
                                $chat->message = file_get_contents("greeting.txt");
                                $chat->message = str_replace("{{user}}", "Ionvop", $chat->message);
                                $chat->message = str_replace("{{char}}", "Ero-chan", $chat->message);
                            
                            echo RenderChat($chat);

                            foreach ($chats as $element) {
                                $chat = new stdClass();

                                if ($element->role == "user") {
                                    $chat->avatar = "assets/user.png";
                                    $chat->name = "Ionvop";
                                    $chat->message = $element->content;
                                } else if ($element->role == "assistant") {
                                    $chat->avatar = "assets/default.jpg";
                                    $chat->name = "Ero-chan";
                                    $chat->message = $element->content;
                                } else {
                                    $chat->avatar = "assets/default.jpg";
                                    $chat->name = "404";
                                    $chat->message = "Not found";
                                }

                                echo RenderChat($chat);
                            }
                        ?>
                        <div class="chat__render__last">

                        </div>
                    </div>
                    <div class="chat__input">
                        <form method="post">
                            <div class="chat__input__container">
                                <div class="chat__input__text">
                                    <textarea name="reply" class="-textarea" oninput="autoResize(this)"></textarea>
                                </div>
                                <div class="chat__input__send -center--flex">
                                    <button class="-button" onsubmit="btnSubmit()">
                                        <span class="material-symbols-rounded">
                                            send
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div>

                </div>
            </div>
        </div>
    </body>
    <script src="script.js"></script>
    <script>

        setTimeout(() => {
            document.querySelector(".chat__render").scrollTop = document.querySelector(".chat__render").scrollHeight;
        }, 100);

        function autoResize(element) {
            element.style.height = "5px";
            element.style.height = (element.scrollHeight) + "px";
        }
    </script>
</html>