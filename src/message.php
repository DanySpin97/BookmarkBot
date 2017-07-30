<?php

require_once 'defines.php';

/**
 *  Add the function for processing messages
 */
$message = function ($bot, $message) {

    // Get language
    $bot->local->getLanguageRedis();

    $text = $message->getText();

    // We did not receive an url so we are expecting another message for bookmark data
    switch($bot->status->getStatus()) {

    case GET_NAME:

        // Get message id of the last message bot sent
        $message_id = $bot->redis->get($bot->chat_id . ':message_id');

        // Edit last message sent
        $bot->editMessageText($message_id, $bot->local->getStr('Name_Msg') . $text);

        // Save the name
        $bot->redis->hSet($bot->chat_id . ':bookmark', 'name', $text);

        // Send the user to next step
        $new_message_id = ($bot->sendMessage($bot->local->getStr('SendDescription_Msg'), $bot->keyboard->getBackSkipKeyboard()))['message_id'];

        // Update the bot state
        $bot->status->setStatus(GET_DESC);

        // Update the message id
        $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

        break;

    case GET_DESC:

        // Get message id of the last message bot sent
        $message_id = $bot->redis->get($bot->chat_id . ':message_id');

        // Edit last message sent
        $bot->editMessageText($message_id, $bot->local->getStr('Description_Msg') . $text);

        // Save the description
        $bot->redis->hSet($bot->chat_id . ':bookmark', 'description', $text);

        // Send the user to next step
        $new_message_id = ($bot->sendMessage($bot->local->getStr('SendHashtags_Msg') . $bot->local->getStr('HashtagsExample_Msg'), $bot->keyboard->getBackSkipKeyboard()))['message_id'];

        // Update stats
        $bot->status->setStatus(GET_HASHTAGS);

        // Update the message id
        $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

        break;

    case GET_HASHTAGS:

        // Get message id of the last message bot sent
        $message_id = $bot->redis->get($bot->chat_id . ':message_id');

        // Get hashtags from message
        $hashtags = PhpBotFramework\Entities\Text::getHashtags($text);

        // If there are hashtags in the message
        if (!empty($hashtags)) {

            // Edit last message sent
            $bot->editMessageText($message_id, $bot->local->getStr('Hashtags_Msg') . $bot->formatHashtags($hashtags));

            // Set hashtags
            $bot->hashtags = $hashtags;

            // Save it on the db and get the id
            $bot->saveBookmark();

            // Update stats
            $bot->status->setStatus(MENU);

            // Add keyboard to edit the bookmark
            $bot->addEditBookmarkKeyboard();

            // Send the bookmark just created
            $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

            // Delete junk in redis
            $bot->redis->delete($bot->chat_id . ':bookmark');
            $bot->redis->delete($bot->chat_id . ':hashtags');
            $bot->redis->delete($bot->chat_id . ':message_id');
            $bot->redis->delete($bot->chat_id . ':index');

        } else {

            // Say the user to resend hashtags
            $new_message_id = ($bot->sendMessage($bot->local->getStr('HashtagsNotValid_Msg') . $bot->local->getStr('HashtagsExample_Msg'), $bot->keyboard->getBackButton()))['message_id'];

            // Set the new message_id
            $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

        }

        break;

    case EDIT_URL:

        // Get bookmark id from redis
        $bookmark_id = $bot->redis->get($bot->chat_id . ':bookmark_id');

        // If there are entities in the message
        if (isset($message['entities'])) {

            // Iterate over all entities
            foreach ($message['entities'] as $index => $entity) {

                // Find an url
                if ($entity['type'] === 'url') {

                    // Edit the url on the databse
                    $sth = $bot->database->pdo->prepare('UPDATE Bookmark SET url = :url WHERE id = :bookmark_id');
                    $sth->bindParam(':url', $text);
                    $sth->bindParam(':bookmark_id', $bookmark_id);

                    try {

                        $sth->execute();

                    } catch (PDOException $e) {

                        echo $e->getMessage();

                    }

                    // Close connection
                    $sth = null;

                    // Get bookmark from database
                    $bot->getBookmark($bookmark_id);

                    // Edit the last message showing the new url
                    $bot->editMessageText($bot->redis->get($bot->chat_id . ':message_id'), $bot->local->getStr('NewUrl_Msg') . $text);

                    // Delete message id as we don't need it anymore
                    $bot->redis->delete($bot->chat_id . ':message_id');
                    $bot->redis->delete($bot->chat_id . ':bookmark_id');

                    // Get keyboard
                    $bot->addEditBookmarkKeyboard();

                    // Send the updated bookmark to the user
                    $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

                    // Edit the message in the channel
                    $bot->updateBookmarkMessage();

                    // We edited the message so we can exit the function
                    return;

                }

            }

        }

        // We did not find urls, so ask the user to resend them
        // Add a back button including the bookmark id
        $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $bookmark_id);

        // Say the user to send the new url
        $new_message_id = ($bot->sendMessage($bot->local->getStr('UrlNotValid_Msg'), $bot->keyboard->get()))['message_id'];

        // Set the new message_id
        $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

        // Update stats
        $bot->status->setStatus(MENU);

        break;

    case EDIT_NAME:

        // Get bookmark id from redis
        $bookmark_id = $bot->redis->get($bot->chat_id . ':bookmark_id');

        // Edit name on the database
        $sth = $bot->database->pdo->prepare('UPDATE Bookmark SET name = :name WHERE id = :bookmark_id');
        $sth->bindParam(':name', $text);
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Close connection
        $sth = null;

        // Get bookmark from database
        $bot->getBookmark($bookmark_id);

        // Edit the last message showing the new url
        $bot->editMessageText($bot->redis->get($bot->chat_id . ':message_id'), $bot->local->getStr('NewName_Msg') . $text);

        // Delete message id as we don't need it anymore
        $bot->redis->delete($bot->chat_id . ':message_id');
        $bot->redis->delete($bot->chat_id . ':bookmark_id');

        // Get keyboard
        $bot->addEditBookmarkKeyboard();

        // Send the updated bookmark to the user
        $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

        // Update stats
        $bot->status->setStatus(MENU);

        break;

    case EDIT_DESCRIPTION:

        // Get bookmark id from redis
        $bookmark_id = $bot->redis->get($bot->chat_id . ':bookmark_id');

        // Edit the description on the database
        $sth = $bot->database->pdo->prepare('UPDATE Bookmark SET description = :description WHERE id = :bookmark_id');
        $sth->bindParam(':description', $text);
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Close connection
        $sth = null;

        // Get bookmark from database
        $bot->getBookmark($bookmark_id);

        // Edit the last message showing the new url
        $bot->editMessageText($bot->redis->get($bot->chat_id . ':message_id'), $bot->local->getStr('NewDescription_Msg') . $text);

        // Delete message id as we don't need it anymore
        $bot->redis->delete($bot->chat_id . ':message_id');
        $bot->redis->delete($bot->chat_id . ':bookmark_id');

        // Get keyboard
        $bot->addEditBookmarkKeyboard();

        // Send the updated bookmark to the user
        $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

        // Update stats
        $bot->status->setStatus(MENU);

        break;

    case EDIT_HASHTAGS:

        // Get message id of the last message bot sent
        $message_id = $bot->redis->get($bot->chat_id . ':message_id');

        // Get hashtags from message
        $hashtags = PhpBotFramework\Entities\Text::getHashtags($text);

        // If there are hashtags in the message
        if (!empty($hashtags)) {

            // Edit last message sent
            $bot->editMessageText($message_id, $bot->local->getStr('Hashtags_Msg') . $bot->formatHashtags($hashtags));

            // Get bookmark data from redis
            $bookmark_id = $bot->redis->get($bot->chat_id . ':bookmark_id');

            // Update stats
            $bot->status->setStatus(MENU);

            // Delete hashtags on database for the bookmark
            $sth = $bot->database->pdo->prepare('DELETE FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id');
            $sth->bindParam(':bookmark_id', $bookmark_id);

            try {

                $sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            // Close connection
            $sth = null;

            // Get bookmark from db
            $bot->getBookmark($bookmark_id);

            // Set bookmark hashtags to the ones we just received
            $bot->hashtags = $hashtags;

            // Add new hashtags to the bookmark
            $bot->saveHashtags();

            // Add keyboard to edit the bookmark
            $bot->addEditBookmarkKeyboard();

            // Add a menu button
            $bot->keyboard->addButton($bot->local->getStr('Menu_Button'), 'callback_data', 'menu');

            // Send the bookmark just created
            $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

            // Delete junk in redis
            $bot->redis->delete($bot->chat_id . ':bookmark_id');
            $bot->redis->delete($bot->chat_id . ':message_id');

        } else {

            // Say the user to resend hashtags
            $new_message_id = ($bot->sendMessage($bot->local->getStr('HashtagsNotValid_Msg') . $bot->local->getStr('HashtagsExample_Msg'), $bot->keyboard->getBackButton()))['message_id'];

            // Set the new message_id
            $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

        }

        break;

    case CHANGE_CHANNEL:

        // Delete all bookmark in channel
        $bot->deleteAllBookmarkChannel();

        //Delete channel from user
        $sth = $bot->database->pdo->prepare('UPDATE TelegramUser SET channel_id = 0 WHERE chat_id = :chat_id');

        $chat_id = $bot->chat_id;
        $sth->bindParam(':chat_id', $chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $sth = null;

        // No break
    case ADD_CHANNEL:

        // If the message has been forwared from a channel
        if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {

            // If the channel is a public one
            if (strpos($message['forward_from_chat']['id'], '@') !== false) {

                // Just remove the first char ('@')
                $strip_char = 1;

            } else {

                // Remove '-100' prefix
                $strip_char = 3;

            }

            // Get channel id from forwarding, stripping by what've just checked
            $channel_id = substr($message['forward_from_chat']['id'], $strip_char);

            // Is the bot administrator in the channel?
            if ($bot->getChat($message['forward_from_chat']['id']) === false) {

                // Say the user to add the bot as admin
                $new_message_id = ($bot->sendMessage($bot->local->getStr('BotNotAdmin_Msg'), $bot->keyboard->getBackButton()))['message_id'];

                $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

                // We've done with channel adding, return
                return;

            }

            // Get admin from channel
            $administrators = $bot->getChatAdministrators($message['forward_from_chat']['id']);

            // Flag to check if the user is admin of the channel
            $is_user_admin = false;

            // Iterate over all admins
            foreach ($administrators as $index => $chat_member) {

                // Is the admin the same as this user?
                if ($chat_member['user']['id'] === $bot->chat_id) {

                    $is_user_admin = true;

                    break;

                }

            }

            // The user is a channel admin
            if ($is_user_admin) {

                // Update the channel id on database
                $sth = $bot->database->pdo->prepare('UPDATE TelegramUser SET channel_id = :channel_id WHERE chat_id = :chat_id');
                $sth->bindParam(':channel_id', $channel_id);

                $chat_id = $bot->chat_id;
                $sth->bindParam(':chat_id', $chat_id);

                try {

                    $sth->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                // Get the message id of the last message in chat with the user
                $message_id = $bot->redis->get($bot->chat_id . ':message_id');

                // Tell the user the new channel title
                $bot->editMessageText($message_id, $bot->local->getStr('ChannelTitle_Msg') . $message['forward_from_chat']['title']);

                // Send all bookmark in channel
                $bot->updateBookmarkChannel();

                // Update the message with the info about the channel
                $bot->sendMessage($bot->local->getStr('ChannelAdded_Msg') . NEW_LINE . $bot->getChannelData($channel_id), $bot->keyboard->get());

                // Delete junk
                $bot->redis->delete($bot->chat_id . ':message_id');

                $bot->status->setStatus(MENU);

            } else {

                // Button to go to the menu
                $bot->keyboard->addButton($bot->local->getStr('Menu_Button'), 'callback_data', 'menu');

                // Say the user he is not admin
                $bot->sendMessage($bot->local->getStr('UserNotAdmin_Msg'), $bot->keyboard->get());

            }

        } else {

            // Button to go to the menu
            $bot->keyboard->addButton($bot->local->getStr('Menu_Button'), 'callback_data', 'menu');

            // Say the user the message hasn't been forwarded from a channel
            $bot->sendMessage($bot->local->getStr('NotChannel_Msg'), $bot->keyboard->get());

        }

        break;

    default:

        // If there are entities in the message
        if (isset($message['entities'])) {

            // Iterate over all entities
            foreach ($message['entities'] as $index => $entity) {

                // Find an url
                if ($entity['type'] === 'url') {

                    // Get the url from the message
                    $url = $bot->removeHttp(mb_substr($text, $entity['offset'], $entity['length']));

                    // Save the url
                    $bot->redis->hSet($bot->chat_id . ':bookmark', 'url', $url);

                    // Say the user to insert the name for this bookmark
                    $new_message_id = $bot->sendMessage($bot->local->getStr('Url_Msg') . $url . NEW_LINE . $bot->local->getStr('SendName_Msg'), $bot->keyboard->getBackButton())['message_id'];

                    // Update the message id
                    $bot->redis->set($bot->chat_id . ':message_id', $new_message_id);

                    // Change status
                    $bot->status->setStatus(GET_NAME);

                    // We got what we want, so end the script
                    return;
                }
            }
        }
        break;
    }
};
