<?php

$Gpt = "F:/Ionvop/Documents/VSCode Projects/20230914/main.py";

// chat: {avatar: "", name: "", message: ""}
// return: html
function RenderChat($chat) {
    $htmlentities = 'htmlentities';

    return <<<HTML
        <div class="chat__item">
            <div class="chat__item__container">
                <div class="chat__item__avatar">
                    <img src="{$htmlentities($chat->avatar)}">
                </div>
                <div class="chat__item__panel">
                    <div class="chat__item__panel__name">
                        {$htmlentities($chat->name)}
                    </div>
                    <div class="chat__item__panel__message">
                        {$htmlentities($chat->message)}
                    </div>
                </div>
            </div>
        </div>
    HTML;
}

function GetChats() {
    $chats = file_get_contents("chat.json");
    $chats = json_decode($chats);
    return $chats;
}

function Alert($message) {
    exit("<script>alert(\"{$message}\"); window.history.back();</script>");
}

function Debug() {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function Breakpoint($message) {
    header("Content-type: application/json");
    print_r($message);
    exit();
}

?>