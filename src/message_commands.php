<?php

// Called on /start message
$start_closure = function($bot, $message) {

    // Is the user registred in the database?
    $sth = $bot->pdo->prepare('SELECT COUNT(chat_id) FROM "User" WHERE chat_id = :chat_id');

    $chat_id = $bot->getChatID();
    $sth->bindParam(':chat_id', $chat_id);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $user_exists = $sth->fetchColumn();

    $sth = null;

    if ($user_exists != false) {

        $bot->getLanguageRedis();

        // Send the user the menu message
        $bot->sendMessage($bot->menuMessage(), $bot->keyboard->get());

        // Delete junk in redis
        $bot->redis->delete($bot->getChatID() . ':bookmark');
        $bot->redis->delete($bot->getChatID() . ':hashtags');
        $bot->redis->delete($bot->getChatID() . ':message_id');
        $bot->redis->delete($bot->getChatID() . ':index');
        $bot->redis->delete($bot->getChatID() . ':bookmark_id');

        $bot->setStatus(MENU);

    } else {

        // Iterate over all languages
        foreach ($bot->local as $language_index => $localization) {

            // Add a button for each
            $bot->keyboard->addLevelButtons(['text' => $localization['Language'], 'callback_data' => 'cls_' . $language_index]);

        }

        // Send the start message to the user
        $bot->sendMessage($bot->local['en']['Start_Msg'], $bot->keyboard->get());

    }

};

$help_msg_closure = function($bot, $message) {

    $bot->keyboard->AddLevelButtons(['text' => $bot->local[$bot->getLanguageRedis()]['Menu_Button'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['Help_Msg'], $bot->keyboard->get());

};

$about_msg_closure = function($bot, $message) {

    $bot->getLanguageRedis();

    $bot->keyboard->addButton($bot->local[$bot->language]['Contact_Button'], 'url', 't.me/danyspin97');
    $bot->keyboard->addButton($bot->local[$bot->language]['Framework_Button'], 'url', 'github.com/danyspin97/PhpBotFramework');

    $bot->keyboard->addLevelButtons(['text' => $bot->local[$bot->language]['Menu_Button'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};
