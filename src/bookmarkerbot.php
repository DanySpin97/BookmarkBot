<?php

// Define bot state
define("MENU", 0);
define("GET_NAME", 1);
define("GET_DESC", 2);
define("GET_HASHTAGS", 3);
define("EDIT_URL", 4);
define("EDIT_NAME", 5);
define("EDIT_DESCRIPTION", 6);
define("EDIT_HASHTAGS", 7);
define("LANGUAGE", 8);

// Define costant for /n in Telegram Messages
define("NEW_LINE", '
');


// The bot class
class BookmarkerBot extends DanySpin97\PhpBotFramework\Bot {

    // Add the function for processing messages
    protected function processMessage($message) {

        // Get language
        $this->getLanguageRedisAsCache();

        // We did not receive an url so we are expecting another message for bookmark data
        switch($this->getStatus()) {

            case GET_NAME:

                // Get message id of the last message bot sent
                $message_id = $this->redis->get($this->chat_id . ':message_id');

                // Edit last message sent
                $this->editMessageText($message_id, $this->local[$this->language]['Name_Msg'] . $message['text']);

                // Save the name
                $this->redis->hSet($this->chat_id . ':bookmark', 'name', $message['text']);

                // Send the user to next step
                $new_message_id = ($this->sendMessage($this->local[$this->language]['SendDescription_Msg'], $this->keyboard->getBackSkipKeyboard()))['message_id'];

                // Update the bot state
                $this->setStatus(GET_DESC);

                // Update the message id
                $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                break;

            case GET_DESC:

                // Get message id of the last message bot sent
                $message_id = $this->redis->get($this->chat_id . ':message_id');

                // Edit last message sent
                $this->editMessageText($message_id, $this->local[$this->language]['Description_Msg'] . $message['text']);

                // Save the description
                $this->redis->hSet($this->chat_id . ':bookmark', 'description', $message['text']);

                // Send the user to next step
                $new_message_id = ($this->sendMessage($this->local[$this->language]['SendHashtags_Msg'], $this->keyboard->getBackSkipKeyboard()))['message_id'];

                // Update stats
                $this->setStatus(GET_HASHTAGS);

                // Update the message id
                $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                break;

            case GET_HASHTAGS:

                // Get message id of the last message bot sent
                $message_id = $this->redis->get($this->chat_id . ':message_id');

                // Get hashtags from message
                $hashtags = DanySpin97\PhpBotFramework\Utility::getHashtags($message['text']);

                // If there are hashtags in the message
                if (!empty($hashtags)) {

                    // Edit last message sent
                    $this->editMessageText($message_id, $this->local[$this->language]['Hashtags_Msg'] . $this->formatHashtags($hashtags));

                    // Get bookmark data from redis
                    $bookmark = $this->redis->hGetAll($this->chat_id . ':bookmark');

                    // Save it on the db and get the id
                    $bookmark['id'] = $this->saveBookmark($bookmark, $hashtags);

                    // Update stats
                    $this->setStatus(MENU);

                    // Add keyboard to edit the bookmark
                    $this->addEditBookmarkKeyboard($bookmark, $hashtags);

                    // Add a menu button
                    $this->keyboard->addButton($this->local[$this->language]['Menu_Button'], 'callback_data', 'menu');

                    // Send the bookmark just created
                    $this->sendMessage($this->formatBookmark($bookmark, $hashtags), $this->keyboard->get());

                    // Delete junk in redis
                    $this->redis->delete($this->chat_id . ':bookmark');
                    $this->redis->delete($this->chat_id . ':hashtags');
                    $this->redis->delete($this->chat_id . ':message_id');
                    $this->redis->delete($this->chat_id . ':index');

                } else {

                    // Say the user to resend hashtags
                    $new_message_id = ($this->sendMessage($this->local[$this->language]['HashtagsNotValid_Msg'], $this->keyboard->getBackButton()))['message_id'];

                    // Set the new message_id
                    $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                }

                break;

            case EDIT_URL:

                // Get bookmark id from redis
                $bookmark_id = $this->redis->get($this->chat_id . ':bookmark_id');

                // If there are entities in the message
                if (isset($message['entities'])) {

                    // Iterate over all entities
                    foreach ($message['entities'] as $index => $entity) {

                        // Find an url
                        if ($entity['type'] === 'url') {

                            // Edit the url on the databse
                            $sth = $this->pdo->prepare('UPDATE Bookmark SET url = :url WHERE id = :bookmark_id');
                            $sth->bindParam(':url', $message['text']);
                            $sth->bindParam(':bookmark_id', $bookmark_id);
 
                            try {
             
                                $sth->execute();
 
                            } catch (PDOException $e) {
 
                                echo $e->getMessage();
 
                            }
 
                            // Close connection
                            $sth = null;
 
                            // Get bookmark from database
                            $this->getBookmark($bookmark_id);
 
                            // Edit the last message showing the new url
                            $this->editMessageText($this->redis->get($this->chat_id . ':message_id'), $this->local[$this->language]['NewUrl_Msg'] . $message['text']);
 
                            // Delete message id as we don't need it anymore
                            $this->redis->delete($this->chat_id . ':message_id');
                            $this->redis->delete($this->chat_id . ':bookmark_id');
 
                            // Get keyboard
                            $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);
 
                            // Send the updated bookmark to the user
                            $this->sendMessage($this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

                            // We edited the message so we can exit the function
                            return;

                        }

                    }

                }

                // We did not find urls, so ask the user to resend them
                // Add a back button including the bookmark id
                $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $bookmark_id);

                // Say the user to send the new url
                $new_message_id = ($this->sendMessage($this->local[$this->language]['UrlNotValid_Msg'], $this->keyboard->get()))['message_id'];

                // Set the new message_id
                $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                // Update stats
                $this->setStatus(MENU);

                break;

            case EDIT_NAME:

                // Get bookmark id from redis
                $bookmark_id = $this->redis->get($this->chat_id . ':bookmark_id');

                // Edit name on the database
                $sth = $this->pdo->prepare('UPDATE Bookmark SET name = :name WHERE id = :bookmark_id');
                $sth->bindParam(':name', $message['text']);
                $sth->bindParam(':bookmark_id', $bookmark_id);
 
                try {
             
                    $sth->execute();
 
                } catch (PDOException $e) {
 
                    echo $e->getMessage();
 
                }
 
                // Close connection
                $sth = null;

                // Get bookmark from database
                $this->getBookmark($bookmark_id);
 
                // Edit the last message showing the new url
                $this->editMessageText($this->redis->get($this->chat_id . ':message_id'), $this->local[$this->language]['NewName_Msg'] . $message['text']);

                // Delete message id as we don't need it anymore
                $this->redis->delete($this->chat_id . ':message_id');
                $this->redis->delete($this->chat_id . ':bookmark_id');
 
                // Get keyboard
                $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);

                // Send the updated bookmark to the user
                $this->sendMessage($this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

                // Update stats
                $this->setStatus(MENU);

                break;

            case EDIT_DESCRIPTION:

                // Get bookmark id from redis
                $bookmark_id = $this->redis->get($this->chat_id . ':bookmark_id');

                // Edit the description on the database
                $sth = $this->pdo->prepare('UPDATE Bookmark SET description = :description WHERE id = :bookmark_id');
                $sth->bindParam(':description', $message['text']);
                $sth->bindParam(':bookmark_id', $bookmark_id);
 
                try {
             
                    $sth->execute();
 
                } catch (PDOException $e) {
 
                    echo $e->getMessage();
 
                }
 
                // Close connection
                $sth = null;

                // Get bookmark from database
                $this->getBookmark($bookmark_id);
 
                // Edit the last message showing the new url
                $this->editMessageText($this->redis->get($this->chat_id . ':message_id'), $this->local[$this->language]['NewDescription_Msg'] . $message['text']);

                // Delete message id as we don't need it anymore
                $this->redis->delete($this->chat_id . ':message_id');
                $this->redis->delete($this->chat_id . ':bookmark_id');
 
                // Get keyboard
                $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);

                // Send the updated bookmark to the user
                $this->sendMessage($this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

                // Update stats
                $this->setStatus(MENU);

                break;

            case EDIT_HASHTAGS:

                // Get message id of the last message bot sent
                $message_id = $this->redis->get($this->chat_id . ':message_id');

                // Get hashtags from message
                $hashtags = DanySpin97\PhpBotFramework\Utility::getHashtags($message['text']);

                // If there are hashtags in the message
                if (!empty($hashtags)) {

                    // Edit last message sent
                    $this->editMessageText($message_id, $this->local[$this->language]['Hashtags_Msg'] . $this->formatHashtags($hashtags));

                    // Get bookmark data from redis
                    $bookmark_id = $this->redis->get($this->chat_id . ':bookmark_id');

                    // Update stats
                    $this->setStatus(MENU);

                    // Delete hashtags on database for the bookmark
                    $sth = $this->pdo->prepare('DELETE FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id');
                    $sth->bindParam(':bookmark_id', $bookmark_id);

                    try {

                        $sth->execute();

                    } catch (PDOException $e) {

                        echo $e->getMessage();

                    }

                    // Close connection
                    $sth = null;

                    // Add new hashtags to the bookmark
                    $this->saveHashtags($bookmark_id, $hashtags);

                    // Get bookmark from db
                    $this->getBookmark($bookmark_id);

                    // Add keyboard to edit the bookmark
                    $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);

                    // Add a menu button
                    $this->keyboard->addButton($this->local[$this->language]['Menu_Button'], 'callback_data', 'menu');

                    // Send the bookmark just created
                    $this->sendMessage($this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

                    // Delete junk in redis
                    $this->redis->delete($this->chat_id . ':bookmark_id');
                    $this->redis->delete($this->chat_id . ':message_id');

                } else {

                    // Say the user to resend hashtags
                    $new_message_id = ($this->sendMessage($this->local[$this->language]['HashtagsNotValid_Msg'], $this->keyboard->getBackButton()))['message_id'];

                    // Set the new message_id
                    $this->redis->set($this->chat_id . ':message_id', $new_message_id);


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
                            $url = $this->remove_http(substr($message['text'], $entity['offset'], $entity['length']));

                            // Save the url
                            $this->redis->hSet($this->chat_id . ':bookmark', 'url', $url);

                            // Say the user to insert the name for this bookmark
                            $new_message_id = $this->sendMessage($this->local[$this->language]['Url_Msg'] . $url . NEW_LINE . $this->local[$this->language]['SendName_Msg'], $this->keyboard->getBackButton())['message_id'];

                            // Update the message id
                            $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                            // Change status
                            $this->setStatus(GET_NAME);

                            // We got what we want, so end the script
                            return;

                        }

                    }

                }

                break;

        }

    }

    // Process all callback queries received
    protected function processCallbackQuery($callback_query) {

        // Get language from redis
        $this->getLanguageRedisAsCache();

        // If data is set
        if (isset($callback_query['data'])) {

            // If the user is going back to see a bookmark, from the edit menu
            if (strpos($callback_query['data'], 'back') !== false) {

                // Get id from back (eg: back_id)
                $bookmark_id = (explode('_', $callback_query['data'], 2))[1];

                // Get bookmark and hashtags related
                $this->getBookmark($bookmark_id);

                // Add keyboard for editing bookmark
                $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);

                // If the user was browsing the bookmarks
                if ($this->redis->exists($this->chat_id . ':index')) {

                    // add a back button
                    $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back');

                }

                // Add a button to go to the menu
                $this->keyboard->addButton($this->local[$this->language]['Menu_Button'], 'callback_data', 'menu');

                // Show the bookmark to the user
                $this->editMessageText($callback_query['message']['message_id'], $this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

                // Change status
                $this->setStatus(MENU);

            // Is it a button to edit a bookmark?
            } elseif (strpos($callback_query['data'], 'edit') !== false) {

                // Get what the user want to edit (eg. edit_url_idbookmark)
                $data = explode('_', $callback_query['data']);

                // Update the message id in redis
                $this->redis->set($this->chat_id . ':message_id', $callback_query['message']['message_id']);

                // Add the id of the bookmark to edit
                $this->redis->set($this->chat_id . ':bookmark_id', $data[2]);

                switch ($data[1]) {

                    case 'url':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['EditUrl_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_URL);

                        break;

                    case 'name':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['EditName_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_NAME);

                        break;

                    case 'desc':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['DeleteDescription_Button'], 'callback_data', 'delete_desc_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['EditDescription_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_DESCRIPTION);

                        break;

                    case 'hashtags':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['DeleteHashtags_Button'], 'callback_data', 'delete_hashtags_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['EditHashtags_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_HASHTAGS);

                        break;

                }

            // Check if the user is adding description or hashtags to the bookmark
            }  elseif (strpos($callback_query['data'], 'add') !== false) {

                // Get what the user want to edit (eg. edit_url_idbookmark)
                $data = explode('_', $callback_query['data']);

                // Update the message id in redis
                $this->redis->set($this->chat_id . ':message_id', $callback_query['message']['message_id']);

                // Add the id of the bookmark to edit
                $this->redis->set($this->chat_id . ':bookmark_id', $data[2]);

                switch ($data[1]) {

                    case 'desc':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['AddDescription_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_DESCRIPTION);

                        break;

                    case 'hashtags':

                        // Add a back button including the bookmark id
                        $this->keyboard->addButton($this->local[$this->language]['Back_Button'], 'callback_data', 'back_' . $data[2]);

                        // Say the user to send the new url
                        $this->editMessageText($callback_query['message']['message_id'], $this->local[$this->language]['AddHashtags_Msg'], $this->keyboard->get());

                        // Prepare the bot to receive the new url
                        $this->setStatus(EDIT_HASHTAGS);

                        break;

                }

            // Check if the user is deleting bookmark description or hashtag
            } elseif (strpos($callback_query['data'], 'delete') !== false) {

                // Get what the user want to edit (eg. edit_url_idbookmark)
                $data = explode('_', $callback_query['data']);

                switch ($data[1]) {

                    case 'desc':

                        // Set description null for this bookmark
                        $sth = $this->pdo->prepare("UPDATE Bookmark SET description = 'NULL' WHERE id = :bookmark_id");
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
                        $sth = $this->pdo->prepare('DELETE FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id');
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
                $this->getBookmark($data[2]);
 
                // Get keyboard
                $this->addEditBookmarkKeyboard($this->bookmark, $this->hashtags);
 
                // Send the updated bookmark to the user
                $this->editMessageText($callback_query['message']['message_id'], $this->formatBookmark($this->bookmark, $this->hashtags), $this->keyboard->get());

            // Check if the user choosed a language (choose language start)
            } elseif (strpos($callback_query['data'], 'cls') !== false) {

                // If we could add the user
                if ($this->addUser($this->chat_id)) {

                    // Get the last two characters (the language choosed)
                    $this->setLanguageRedisAsCache(substr($callback_query['data'], -2, 2));

                    // Get the user to the menu
                    $this->editMessageText($callback_query['message']['message_id'], $this->menuMessage(), $this->keyboard->get());

                }

            }

        }

    }

    // Remove http and https from sites
    public function remove_http($url) {

        $disallowed = array('http://', 'https://');

        foreach($disallowed as $d) {

            if(strpos($url, $d) === 0) {

                return str_replace($d, '', $url);

            }

        }

        return $url;

    }

    // Get a bookmark from database passing a bookmark id
    public function getBookmark($bookmark_id) {

        // Get the bookmark from the database
        $sth = $this->pdo->prepare("SELECT id, name, url, description, message_id FROM Bookmark WHERE id = :bookmark_id");
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Get bookmark from result
        $this->bookmark = $sth->fetch();

        $sth = null;

        // Get all tags related to the bookmark
        $sth = $this->pdo->prepare("SELECT tag_id FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id");
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Initialize an empty array for storing hashtags
        $this->hashtags = [];

        // Prepare the query to get the id of each tag related to the bookmark
        $sth2 = $this->pdo->prepare('SELECT name FROM Tag WHERE id = :tag_id');
        // Iterate over all teh results
        while ($row = $sth->fetch()) {

            $sth2->bindParam(':tag_id', $row['tag_id']);

            try {

                $sth2->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            // Get the name
            $hashtag = $sth2->fetchColumn();

            // If the results is valid
            if ($hashtag !== false) {

                // Add the hashtag
                $this->hashtags []= $hashtag;

            }

        }

        // Close statement
        $sth = null;
        $sth2 = null;

    }

    // Format a bookmark
    static public function formatBookmark($bookmark, $hashtags) : string {

        // Add the name of the bookmark with bold formattation
        $message = '<b>' . $bookmark['name'] . '</b>' . NEW_LINE;

        // Add description formatted in italics
        ($bookmark['description'] !== 'NULL') ? ($message .= '<i>' . $bookmark['description'] . '</i>' . NEW_LINE) : null;

        // Add the url
        $message .= NEW_LINE . $bookmark['url'] . NEW_LINE;

        $message .= BookmarkerBot::formatHashtags($hashtags);

        return $message;

    }

    // Format hashtags to send them in the message
    static public function formatHashtags($hashtags) : string {

        // Count how many hashtags we concatenate in this line
        $i = 0;

        $message = '';

        if ($hashtags === null) {

            $hashtags = [];

        }

        // Add the hashtags
        foreach($hashtags as $index => $hashtag) {

            // Concats the hahstags
            $message .= $hashtag . ' ';

            // We added one more
            $i++;

            // Are there 3 hashtags in this line?
            if ($i === 3) {

               // Then go tho the next line
               $message .= NEW_LINE;

            }

        }

        return $message;

    }

    static public function formatItem($item, $keyboard) : string{

        $message = BookmarkerBot::formatBookmark($item, []);

        $keyboard->addButton($item['name'], 'callback_data', $item['id']);

        return $message;

    }

    public function getUserChannel() : bool {

        $sth = $this->pdo->prepare('SELECT channel_id FROM "User" WHERE chat_id = :chat_id');
        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $channel_id = $sth->fetchColumn();

        $sth = null;

        return intval($channel_id);

    }

    // Send a bookmark in the channel of the user
    public function sendBookmarkChannel($bookmark, $hashtags) {

        // If the bookmark has no id (for unknown reasons)
        if (!isset($bookmark['id'])) {

            // Exit the function
            return;

        }

        // Get the channel_id for the current user
        $sth = $this->pdo->prepare('SELECT channel_id FROM "User" WHERE chat_id = :chat_id');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $channel_id = $sth->fetchColumn();

        $sth = null;

        // Check if the user has a channel and the query didn't get errors
        if ($channel_id != false) {

            // Save user id to restore it later
            $previous_chat_id = $this->chat_id;

            // Set the chat_id to channel_id
            $this->chat_id = $channel_id;

            $this->keyboard->clear();

            // Add a button that will let user edit the bookmark
            $this->keyboard->addLevelButtons(['text' => $this->local[$this->language]['Edit_Button'], 'callback_data' => 'editfromchannel']);

            // Send the message to the channel
            $new_message_id = ($this->sendMessage($this->formatBookmark($bookmark, $hashtags), $this->keyboard->get()))['message_id'];

            // Add the message id to the bookmark
            $sth = $this->pdo->prepare('UPDATE Bookmark SET message_id = :message_id WHERE id = :bookmark_id');

            $sth->bindParam(':message_id', $new_message_id);
            $sth->bindParam(':bookmar_id', $bookmark['id']);

            try {

                $sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            $sth = null;

        }

    }

    // Save a bookmark in the database
    public function saveBookmark($bookmark, $hashtags) : int {

        // Create bookmark
        $sth = $this->pdo->prepare('INSERT INTO Bookmark (name, description, url, user_id) VALUES (:name, :description, :url, :user_id) RETURNING id');
        $bookmark = $this->redis->hGetAll($this->getChatID() . ':bookmark');

        $sth->bindParam(':name', $bookmark['name']);
        $sth->bindParam(':description', $bookmark['description']);
        $sth->bindParam(':url', $bookmark['url']);
        $sth->bindParam(':user_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            $e->getMessage();

        }

        // Get id of the bookmark created
        $bookmark_id = $sth->fetch()['id'];

        // Close connection
        $sth = null;

        // Save hashtags
        $this->saveHashtags($bookmark_id, $hashtags);

        if ($bookmark_id !== false) {

            return $bookmark_id;

        }

        return 0;

    }

    // Save hashtags on database
    public function saveHashtags($bookmark_id, $hashtags) {

        // Is there a tag with a specific name?
        $sth = $this->pdo->prepare('SELECT id FROM Tag WHERE name = :name LIMIT 1');

        // Create a tag
        $sth2 = $this->pdo->prepare('INSERT INTO Tag (name) VALUES (:name) RETURNING id');

        // Tag a bookmark passing bookmard and tag ids
        $sth3 = $this->pdo->prepare('INSERT INTO Bookmark_Tag (bookmark_id, tag_id) VALUES (:bookmark_id, :tag_id)');

        // For each hashtag added to the bookmark
        foreach ($hashtags as $index => $hashtag) {

            // Check if there is any with the same name
            $sth->bindParam(':name', $hashtag);

            try {

                $sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            // Get the id of the tag
            $tag_id = $sth->fetchColumn();

            // Is the id valid?
            if ($tag_id == false) {

                // Create a tag
                $sth2->bindParam(':name', $hashtag);

                try {

                    $sth2->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                // Get the id
                $tag_id = $sth2->fetch()['id'];

            }

            // Tag the bookmark
            $sth3->bindParam(':bookmark_id', $bookmark_id);

            $sth3->bindParam(':tag_id', $tag_id);

            try {

                $sth3->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

        }

        $sth = null;

        $sth2 = null;

        $sth3 = null;

    }

    public function menuMessage() {

        // Add menu buttons
        $this->keyboard->addButton($this->local[$this->language]['Browse_Button'], 'callback_data', 'browse');
        $this->keyboard->changeRow();

        if ($this->getUserChannel() != false) {

            $this->keyboard->addButton($this->local[$this->language]['EditChannel_Button'], 'callback_data', 'channel');

        } else {

            $this->keyboard->addButton($this->local[$this->language]['AddChannel_Button'], 'callback_data', 'channel');

        }

        $this->keyboard->changeRow();

        $this->keyboard->addButton($this->local[$this->language]['Language_Button'], 'callback_data', 'language');

        $this->keyboard->addLevelButtons(['text' => $this->local[$this->language]['Help_Button'], 'callback_data' => 'help'], ['text' => $this->local[$this->language]['About_Button'], 'callback_data' => 'about']);

        return $this->local[$this->language]['Menu_Msg'];

    }

    // Add the keyboard for editing bookmarks as a side effect
    public function addEditBookmarkKeyboard($bookmark, $hashtags) {

        // Add button for editing bookmark's url and name
        $this->keyboard->addButton($this->local[$this->language]['EditUrl_Button'], 'callback_data', 'edit_url_' . $bookmark['id']);
        $this->keyboard->addButton($this->local[$this->language]['EditName_Button'], 'callback_data', 'edit_name_' . $bookmark['id']);

        // Change keyboard row
        $this->keyboard->changeRow();

        // Does the bookmark has a description?
        if ($bookmark['description'] !== 'NULL') {

            // Add a button for editing the description
            $this->keyboard->addButton($this->local[$this->language]['EditDescription_Button'], 'callback_data', 'edit_desc_' . $bookmark['id']);

        } else {

            // Add a button to add the description
            $this->keyboard->addButton($this->local[$this->language]['AddDescription_Button'], 'callback_data', 'add_desc_' . $bookmark['id']);

        }

        // Does the bookmark has hashtags?
        if (!empty($hashtags)) {

            // Add a button to edit hashtags
            $this->keyboard->addButton($this->local[$this->language]['EditHashtags_Button'], 'callback_data', 'edit_hashtags_' . $bookmark['id']);

        } else {

            // Add a button to add hashtags
            $this->keyboard->addButton($this->local[$this->language]['AddHashtags_Button'], 'callback_data', 'add_hashtags_' . $bookmark['id']);

        }

        // Change keyboard row
        $this->keyboard->changeRow();

        // Add a button to share the bookmark
        $this->keyboard->addButton($this->local[$this->language]['Share_Button'], 'switch_inline_query', $bookmark['name']);

        $this->keyboard->changeRow();

    }

}

$start_closure = function($bot, $message) {

    // Is the user registred in the database?
    $sth = $bot->pdo->prepare('SELECT COUNT(chat_id) FROM "User" WHERE chat_id = :chat_id');
    $sth->bindParam(':chat_id', $message['from']['id']);
    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $user_exists = $sth->fetchColumn();

    $sth = null;

    if ($user_exists != false) {

        $bot->getLanguageRedisAsCache();

        // Send the user the menu message
        $bot->sendMessage($bot->menuMessage(), $bot->keyboard->get());

        // Delete junk in redis
        $bot->redis->delete($bot->getChatID() . ':bookmark');
        $bot->redis->delete($bot->getChatID() . ':hashtags');
        $bot->redis->delete($bot->getChatID() . ':message_id');

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

$menu_closure = function($bot, $callback_query) {

    $bot->getLanguageRedisAsCache();

    $bot->editMessageText($callback_query['message']['message_id'], $bot->menuMessage(), $bot->keyboard->get());

};

$help_msg_closure = function($bot, $message) {

    $bot->keyboard->AddLevelButtons(['text' => $bot->local[$bot->getLanguageRedisAsCache()]['Menu_Button'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['Help_Msg'], $bot->keyboard->get());

};

$about_msg_closure = function($bot, $message) {

    $bot->keyboard->addLevelButtons(['text' => $bot->local[$bot->getLanguageRedisAsCache()]['Menu_Button'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};

$help_cbq_closure = function($bot, $callback_query) {

    $bot->keyboard->addButton($bot->local[$bot->getLanguageRedisAsCache()]['Menu_Button'], 'callback_data', 'menu');

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['Help_Msg'], $bot->keyboard->get());

};

$about_cbq_closure = function($bot, $callback_query) {

    $bot->keyboard->addButton($bot->local[$bot->getLanguageRedisAsCache()]['Menu_Button'], 'callback_data', 'menu');

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};

$language_closure = function($bot, $callback_query) {

    $bot->getLanguageRedisAsCache();

    $bot->setStatus(LANGUAGE);

    $bot->editMessageText($callback_query['message']['message_id'], $bot->local[$bot->language]['LanguageOption_Msg'], $bot->keyboard->getChooseLanguageKeyboard());

};

$browse_closure = function($bot, $callback_query) {

    $bot->getLanguageRedisAsCache();

    $sth = $bot->pdo->prepare('SELECT id, name, description, url FROM Bookmark');
   
    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $message = DanySpin97\PhpBotFramework\Utility::paginateItems($sth, 1, [$bot, 'formatItem'], 3, $bot->keyboard);
    $bot->editMessageText($callback_query['message']['message_id'], $message, $bot->keyboard->get());

};

$skip_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedisAsCache();

    // Get id of the message which the user pressed on 
    $message_id = $callback_query['message']['message_id'];

    // What is the user skipping?
    switch ($bot->getStatus()) {

        case GET_DESC:

            // Set description NULL for this bookmark
            $bot->redis->hSet($bot->getChatID() . ':bookmark', 'description', 'NULL');

            // Say the user to send the hashtags
            $bot->editMessageText($message_id, $bot->local[$bot->getLanguageRedisAsCache()]['Description_Msg'] . $bot->local[$bot->language]['Skipped_Msg'] . NEW_LINE . $bot->local[$bot->language]['SendHashtags_Msg'], $bot->keyboard->getBackSkipKeyboard());

            // Change status
            $bot->setStatus(GET_HASHTAGS);

            break;

        // User skipped hashtags inserting, save the bookmark
        case GET_HASHTAGS:

            // Get bookmark data from redis
            $bookmark = $bot->redis->hGetAll($bot->getChatID() . ':bookmark');

            $hashtags = [];

            // Save it on the db
            $bookmark['id'] = $bot->saveBookmark($bookmark, $hashtags);

            // Update stats
            $bot->setStatus(MENU);

            // Add keyboard to edit the bookmark
            $bot->addEditBookmarkKeyboard($bookmark, $hashtags);

            // Add a menu button
            $bot->keyboard->addButton($bot->local[$bot->language]['Menu_Button'], 'callback_data', 'menu');

            // Send the bookmark just created
            $bot->editMessageText($callback_query['message']['message_id'], $bot->formatBookmark($bookmark, $hashtags), $bot->keyboard->get());

            // Delete junk in redis
            $bot->redis->delete($bot->getChatID() . ':bookmark');
            $bot->redis->delete($bot->getChatID() . ':message_id');
            $bot->redis->delete($bot->getChatID() . ':index');

            break;

    }

};

$back_closure = function($bot, $callback_query) {

    // Get language
    $bot->getLanguageRedisAsCache();

    // Get id the message which the user pressed the button
    $message_id = $callback_query['message']['message_id'];

    switch ($bot->getStatus()) {

        case GET_NAME:

            // Show the menu to the user
            $bot->editMessageText($message_id, $bot->menuMessage(), $bot->keyboard->get());

            // Change to status as the user is in the menu
            $bot->setStatus(MENU);

            // Delete junk in redis
            $bot->redis->delete($bot->getChatID() . ':bookmark');
            $bot->redis->delete($bot->getChatID() . ':message_id');

            break;

        case GET_DESC:

            // Say the user to insert the name
            $bot->editMessageText($message_id, $bot->local[$bot->language]['SendName_Msg'], $bot->keyboard->getBackButton());

            // Change the status as the user is inserting the name
            $bot->setStatus(GET_NAME);

            break;

        case GET_HASHTAGS:

            // Say the user to insert the description
            $bot->editMessageText($message_id, $bot->local[$bot->language]['SendDescription_Msg'], $bot->keyboard->getBackSkipKeyboard());

            // Change the status as the user is inserting the name
            $bot->setStatus(GET_DESC);

            break;

        case LANGUAGE:

            // Send the user to the menu
            $bot->editMessageText($message_id, $bot->menuMessage(), $bot->keyboard->get());

            // Update status
            $bot->setStatus(MENU);

        default:

            // Paginate data
            break;

    }

};

