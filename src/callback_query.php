<?php

require_once 'defines.php';

/**
 * Process callback queries
 */

$callback_query = function ($bot, $callback_query) {
    // Get language from redis
    $bot->local->getLanguageRedis();

    $cb_data = $callback_query->getData();

    // If data is set
    if (isset($cb_data)) {

        // If the user is going back to see a bookmark, from the edit menu
        if (strpos($cb_data, 'back') !== false) {

            // Get id from back (eg: back_id)
            $bookmark_id = (explode('_', $cb_data, 2))[1];

            // Get bookmark and hashtags related
            $bot->getBookmark($bookmark_id);

            // Add keyboard for editing bookmark
            $bot->addEditBookmarkKeyboard();

            // Show the bookmark to the user
            $bot->editMessageText($callback_query['message']['message_id'], $bot->formatBookmark(), $bot->keyboard->get());

            // Change status
            $bot->status->setStatus(MENU);

            // If the user is browsing hgis bookmarks
        } elseif (strpos($cb_data, 'list') !== false) {

            $data = explode('/', $cb_data);

            // Get all bookmarks
            $sth = $bot->database->pdo->prepare('SELECT id, name, description, url FROM Bookmark WHERE user_id = :chat_id');

            $chat_id = $bot->chat_id;
            $sth->bindParam(':chat_id', $chat_id);

            try {

                $sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            // Paginate the bookmark received
            $message = PhpBotFramework\Utilities\Paginator::paginateItems($sth, $data[1], $bot->keyboard, [$bot, 'formatItem'], ITEMS_PER_PAGE);

            // Add a button to go to the menu
            $bot->keyboard->addLevelButtons(['text' => $bot->local->getStr('Menu_Button'), 'callback_data' => 'menu']);

            // Send the message to the user
            $bot->editMessageText($callback_query['message']['message_id'], $message, $bot->keyboard->get());

            // Update the index on redis db
            $bot->redis->set($bot->chat_id . ':index', $data[1]);

            // Check if the user selected a bookmark
        } elseif (strpos($cb_data, 'id') !== false) {

            // Get bookmark id from callback data
            $bookmark_id = explode('_', $cb_data)[1];

            // Get the bookmark from the database
            $bot->getBookmark($bookmark_id);

            // Get keyboard
            $bot->addEditBookmarkKeyboard();

            // Send the updated bookmark to the user
            $bot->editMessageText($callback_query['message']['message_id'], $bot->formatBookmark(), $bot->keyboard->get());

            // When a user want to edit a ookmark while browsing them from channel
        } elseif (strpos($cb_data, 'editbkch_') !== false) {

            // Get bookmark id from callback data
            $bookmark_id = explode('_', $cb_data)[1];

            // If the bookmark_id has not been set it in $data
            if ($bookmark_id == false) {

                $bot->answerCallbackQuery();

                // Return
                return;

            }

            // Get bookmark from database
            $bot->getBookmark($bookmark_id);

            // Add keyboard buttons
            $bot->addEditBookmarkKeyboard();

            // Send the messatge to the user
            $bot->sendMessage($bot->formatBookmark(), $bot->keyboard->get());

            // Delte redis crap
            $bot->redis->delete("{$bot->chat_id}:index");
            $bot->redis->delete("{$bot->chat_id}:bookmark");

            // Change user status
            $bot->status->setStatus(MENU);

            // Is it a button to edit a bookmark?
        } elseif (strpos($cb_data, 'edit_') !== false) {

            // Get what the user want to edit (eg. edit_url_idbookmark)
            $data = explode('_', $cb_data);

            // Update the message id in redis
            $bot->redis->set($bot->chat_id . ':message_id', $callback_query['message']['message_id']);

            // Add the id of the bookmark to edit
            $bot->redis->set($bot->chat_id . ':bookmark_id', $data[2]);

            switch ($data[1]) {

            case 'url':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('EditUrl_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_URL);

                break;

            case 'name':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('EditName_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_NAME);

                break;

            case 'desc':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('DeleteDescription_Button'), 'callback_data', 'delete_desc_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('EditDescription_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_DESCRIPTION);

                break;

            case 'hashtags':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('DeleteHashtags_Button'), 'callback_data', 'delete_hashtags_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('EditHashtags_Msg') . $bot->local->getStr('HashtagsExample_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_HASHTAGS);

                break;

            }

            // Check if the user is adding description or hashtags to the bookmark
        }  elseif (strpos($cb_data, 'add') !== false) {

            // Get what the user want to edit (eg. edit_url_idbookmark)
            $data = explode('_', $cb_data);

            // Update the message id in redis
            $bot->redis->set($bot->chat_id . ':message_id', $callback_query['message']['message_id']);

            // Add the id of the bookmark to edit
            $bot->redis->set($bot->chat_id . ':bookmark_id', $data[2]);

            switch ($data[1]) {

            case 'desc':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('AddDescription_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_DESCRIPTION);

                break;

            case 'hashtags':

                // Add a back button including the bookmark id
                $bot->keyboard->addButton($bot->local->getStr('Back_Button'), 'callback_data', 'back_' . $data[2]);

                // Say the user to send the new url
                $bot->editMessageText($callback_query['message']['message_id'], $bot->local->getStr('AddHashtags_Msg') . $bot->local->getStr('HashtagsExample_Msg'), $bot->keyboard->get());

                // Prepare the bot to receive the new url
                $bot->status->setStatus(EDIT_HASHTAGS);

                break;

            }

            // The user want to delete a bookmark
        } elseif (strpos($cb_data, 'deletebookmark_') !== false) {

            // If the flag hasn't been set
            if (!$bot->redis->exists("{$bot->chat_id}:deletebookmark_flag")) {

                // Set it
                $bot->redis->setEx("{$bot->chat_id}:deletebookmark_flag", 20, 1);

                // Say the user to click again
                $bot->answerCallbackQuery($bot->local->getStr('DeleteBookmarkWarning_AnswerCallback'), true);

                return;

            }

            // Get what the user want to delete (eg. deletebookmark_idbookmark)
            $data = explode('_', $cb_data);

            // Delete all hashtags
            $sth = $bot->database->pdo->prepare('DELETE FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id');
            $sth->bindParam(':bookmark_id', $data[1]);

            try {

                $sth->execute();

            } catch(PDOException $e) {

                echo $e->getMessage();

            }

            $sth = null;

            $sth = $bot->database->pdo->prepare('SELECT message_id FROM Bookmark WHERE id = :bookmark_id');
            $sth->bindParam(':bookmark_id', $data[1]);

            try {

                $sth->execute();

            } catch(PDOException $e) {

                echo $e->getMessage();

            }

            $message_id = $sth->fetchColumn();

            // If the bookmark has been sent in a channel
            if ($message_id != false) {

                $channel_id = $bot->getChannelID();

                $previous_chat_id = $bot->chat_id;

                $bot->setChatID($channel_id);

                // Say that the bookmark has been deleted
                $bot->editMessageText($message_id, $bot->local->getStr('DeletedBookmarkChannel_Msg'));

                $bot->setChatID($previous_chat_id);

            }

            $sth = null;

            // Delete the bookmark
            $sth = $bot->database->pdo->prepare('DELETE FROM Bookmark WHERE id = :bookmark_id');
            $sth->bindParam(':bookmark_id', $data[1]);

            try {

                $sth->execute();

            } catch(PDOException $e) {

                echo $e->getMessage();

            }

            $sth = null;

            // Go back in bookmark browsing if the user was browsing
            if ($bot->redis->exists("{$bot->chat_id}:index")) {

                $index = $bot->redis->get("{$bot->chat_id}:index");

                // Get all bookmarks
                $sth = $bot->database->pdo->prepare('SELECT id, name, description, url FROM Bookmark WHERE user_id = :chat_id');

                $chat_id = $bot->chat_id;
                $sth->bindParam(':chat_id', $chat_id);

                try {
                    $sth->execute();
                } catch (PDOException $e) {
                    echo $e->getMessage();
                }

                // Paginate the bookmark received
                $message = PhpBotFramework\Utilities\Paginator::paginateItems($sth, $index, $bot->keyboard, [$bot, 'formatItem'], ITEMS_PER_PAGE);

                // Add a button to go to the menu
                $bot->keyboard->addLevelButtons(['text' => $bot->local->getStr('Menu_Button'), 'callback_data' => 'menu']);

                // Send the message to the user
                $bot->editMessageText($callback_query['message']['message_id'], $message, $bot->keyboard->get());

                $sth = null;
            } else {
                // Send the user to the menu
                $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());
            }

            // Delete the flag
            $bot->redis->delete("{$bot->chat_id}:deletebookmark_flag");

            // Say the user the bookmark has been deleted
            $bot->answerCallbackQuery($bot->local->getStr('BookmarkDeleted_AnswerCallback'));

            return;

            // Check if the user is deleting bookmark description or hashtag
        } elseif (strpos($cb_data, 'delete_') !== false) {

            // Get what the user want to edit (eg. edit_url_idbookmark)
            $data = explode('_', $cb_data);

            switch ($data[1]) {

            case 'desc':

                // Set description null for this bookmark
                $sth = $bot->database->pdo->prepare("UPDATE Bookmark SET description = 'NULL' WHERE id = :bookmark_id");
                $sth->bindParam(':bookmark_id', $data[2]);

                try {

                    $sth->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                // Close connection
                $sth = null;

                break;

            case 'hashtags':

                // Delete all hashtags of this bookmark
                $sth = $bot->database->pdo->prepare('DELETE FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id');
                $sth->bindParam(':bookmark_id', $data[2]);

                try {

                    $sth->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                // Close connection
                $sth = null;

                break;

            }

            // Get bookmark from database
            $bot->getBookmark($data[2]);

            // Get keyboard
            $bot->addEditBookmarkKeyboard();

            // Send the updated bookmark to the user
            $bot->editMessageText($callback_query['message']['message_id'], $bot->formatBookmark(), $bot->keyboard->get());

            // Check if the user choosed a language in options
        } elseif (strpos($cb_data, 'cl_') !== false) {

            // Get the last two characters (the language choosed)
            $bot->local->setLanguageRedis(substr($cb_data, -2, 2));

            // Get the user to the menu
            $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());

            // Check if the user choosed a language after clicking /start for the first time (choose language start)
        } elseif (strpos($cb_data, 'cls_') !== false) {
            // If we could add the user
            if ($bot->database->addUser($bot->chat_id)) {
                // Get the last two characters (the language choosed)
                $bot->local->setLanguageRedis(substr($cb_data, -2, 2));

                // Get the user to the menu
                $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());
            }
        }
    }

    $bot->answerCallbackQuery();
};
