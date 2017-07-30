<?php

// Called on /start message
$start_closure = function($bot, $message) {
    // Is the user registred in the database?
    $sth = $bot->database->pdo->prepare('SELECT COUNT(chat_id) FROM TelegramUser WHERE chat_id = :chat_id');

    $sth->bindParam(':chat_id', $bot->chat_id);

    try {
        $sth->execute();
    } catch (PDOException $e) {
        echo $e->getMessage();

    }

    $user_exists = $sth->fetchColumn();

    $sth = null;

    if ($user_exists != false) {
        $bot->local->getLanguageRedis();

        // Send the user the menu message
        $bot->sendMessage($bot->menuMessage(), $bot->keyboard->get());

        // Delete junk in redis
        $bot->redis->delete($bot->getChatID() . ':bookmark');
        $bot->redis->delete($bot->getChatID() . ':hashtags');
        $bot->redis->delete($bot->getChatID() . ':message_id');
        $bot->redis->delete($bot->getChatID() . ':index');
        $bot->redis->delete($bot->getChatID() . ':bookmark_id');

        $bot->status->setStatus(MENU);
    } else {
        // Iterate over all languages
        foreach ($bot->local->local as $language_index => $localization) {
            // Add a button for each
            $bot->keyboard->addButton($localization['Language'], 'callback_data', 'cls_' . $language_index);
            $bot->keyboard->changeRow();
        }

        // Send the start message to the user
        $bot->sendMessage($bot->local->local['en']['Start_Msg'], $bot->keyboard->get());
    }
};

$help_msg_closure = function($bot, $message) {

    $bot->keyboard->AddLevelButtons(['text' => $bot->local->getStr('Menu_Button'), 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local->getStr('Help_Msg'), $bot->keyboard->get());

};

$about_msg_closure = function($bot, $message) {

    $bot->local->getLanguageRedis();

    $bot->keyboard->addButton($bot->local->getStr('Contact_Button'), 'url', 't.me/danyspin97');
    $bot->keyboard->addButton($bot->local->getStr('Framework_Button'), 'url', 'github.com/danyspin97/PhpBotFramework');

    $bot->keyboard->addLevelButtons(['text' => $bot->local->getStr('Menu_Button'), 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local->getStr('About_Msg'), $bot->keyboard->get());

};
