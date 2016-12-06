<?php

// Define bot state
define("MENU", 0);
define("GET_NAME", 1);
define("GET_DESC", 2);
define("GET_HASHTAG", 3);


// The bot class
class BookmarkerBot extends DanySpin97\PhpBotFramework\Bot {

    // Add the function for processing messages
    protected function processMessage($message) {

        // If there are entities in the message
        if (isset($message['entities'])) {

            // Iterate over all entities
            foreach ($message['entities'] as $index => $entity) {

                // Find an url
                if ($entity['type'] === 'url') {

                    // Get the url from the message
                    $url = $this->remove_http(substr($message['text'], $entity['offset'], $entity['length']));

                    // Save the url
                    $redis->set($this->chat_id . ':bookmark', 'url', $url);

                    // Edit the message before this
                    $this->editMessageText($this->redis->get($this->chat_id . ':message_id'), $this->local[$this->language]['Url_Msg'] . $url);

                    // Say the user to insert the name for this bookmark
                    $new_message_id = $this->sendMessage($this->local[$this->language]['SendName_Msg'], $this->keyboard->getMenuKeyboard())['message_id'];

                    // Update the message id
                    $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                    // Change status
                    $this->setStatus(GET_NAME);

                    break;

                }

            }

            // There aren't urls
            // Say the user to resend it
            $new_message_id = $this->sendMessage($this->local[$this->language]['UrlNotValid_Msg'], $this->keyboard->getMenuKeyboard());

            // Update the message id
            $this->redis->set($this->chat_id . ':message_id', $new_message_id);

            // Change status
            $this->setStatus(MENU);

        } else {

            // What are we expecting to receive
            switch($this->getStatus()) {

                case GET_NAME:

                    // Save the name
                    $redis->set($this->chat_id . ':bookmark', 'name', $message['text']);

                    // Send the user to next step
                    $this->sendMessage($this->local['SendDesc_Msg'], $this->keyboard->getBackSkipKeyboard());

                    // Update the bot state
                    $this->setStatus(GET_DESC);

                    break;

                case GET_DESC:

                    // Save the description
                    $redis->set($this->chat_id . ':bookmark', 'description', $message['text']);

                    // Send the user to next step
                    $this->sendMessage($this->local['SendDesc_Msg'], $this->keyboard->getBackSkipKeyboard());

                    $this->setStatus(MENU);

                    break;

                case GET_HASHTAG:

                    // Get hashtags from message
                    $hashtags = DanySpin97\PhpBotFramework\Utility::getHashtags($message['text']);

                    // If there are hashtags in the message
                    if (!empty($hashtags)) {

                        $this->saveBookmark();

                    }

                    break;

            }

        }

    }

    // Process all callback queries received
    protected function processCallbackQuery($callback_query) {

        // Get language from redis
        $this->getLanguageRedisAsCache();

        // If data is set
        if (isset($callback_query['data'])) {

            // Check if the user choosed a language (choose language start)
            if (strpos($callback_query['data'], 'cls')) {

                // If we could add the user
                if ($this->addUser()) {

                    // Get the last two characters (the language choosed)
                    $this->setLanguageRedisAsCache(substr($callback_query['data'], -1, 2));

                    // Get the user to the menu
                    $this->editMessageText($callback_query['message']['message_id'], $this->local["Menu_Msg"], $this->getMenuKeyboard());

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

    // Format a bookmark
    public function formatBookmark($bookmark, $hashtags) : string {

        // Add the name of the bookmark with bold formattation
        $message = '<b>' . $bookmark['name'] . '</b>';

        // Add description formatted in italics
        ($bookmark['description'] !== 'NULL') ? ($message .= NEW_LINE .'<i>' . $bookmark['description'] . '</i>' . NEW_LINE) : null;

        // Add the url
        $message = NEW_LINE . $bookmark['url'];

        // Add the hashtags
        foreach($hashtags as $index => $hashtag) {

            $message .= $hashtag;

        }

        return $message;

    }

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

            $sth = $this->pdo->prepare('UPDATE Bookmark SET message_id = :message_id WHERE id = :bookmark_id');

            $sth->bindParam(':message_id', $new_message_id);
            $sth->bindParam(':bookmar_id', $bookmark['id']);

        }

    }

    // Save a bookmark in the database
    public function saveBookmark($bookmark) {

        // Create bookmark
        $sth = $this->pdo->prepare('INSERT INTO Bookmark (name, description, url, user_id) VALUES (:name, :description, :url, :user_id) RETURNING id');
        $bookmark = $this->redis->get($this->getChatID() . 'bookmark');

        $sth->bindParam(':name', $bookmark['name']);
        $sth->bindParam(':description', $bookmark['description']);
        $sth->bindParam(':url', $bookmark['url']);
        $sth->bindParam(':user_id', $this->getChatID());

        try {

            $sth->execute();

        } catch (PDOException $e) {

            $e->getMessage();

        }

        // Get id of the bookmark created
        $bookmark_id = $sth->fetch()['id'];

        $sth = null;

        // Is there a tag with a specific name?
        $get_name_sth = $this->pdo->prepare('SELECT id FROM Tag WHERE name = :name');

        // Create a tag
        $create_tag_sth = $this->pdo->prepare('INSERT INTO Tag (name) VALUES (:name) RETURNING id');

        // Tag a bookmark passing bookmard and tag ids
        $tag_bookmark_sth = $this->pdo->prepare('INSERT INTO Bookmark_Tag (bookmark_id, tag_id) VALUES (:bookmar_id, :tag_id)');

        // For each hashtag added to the bookmark
        foreach ($hashtags as $index => $hashtag) {

            // Check if there is any with the same name
            $get_name_sth->bindParam(':name', $hashtag);

            try {

                $get_name_sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

            // Get the id of the tag
            $tag_id = $get_name_sth->fetchColumn();

            // Is the id valid?
            if ($tag_id !== false) {

                // Create a tag
                $create_tag_sth->bindParam(':name', $hashtag);

                try {

                    $create_tag_sth->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                // Get the id
                $tag_id = $create_tag_sth->fetch()['id'];

            }

            // Tag the bookmark
            $tag_bookmark_sth->bindParam(':bookmark_id', $bookmark_id);

            $tag_bookmark_sth->bindParam(':tag_id', $tag_id);

            try {

                $tag_bookmark_sth->execute();

            } catch (PDOException $e) {

                echo $e->getMessage();

            }

        }

        $get_name_sth = null;

        $create_tag_sth = null;

        $tag_bookmark_sth = null;

    }

}

$start_closure = function($bot, $message) {

    $bot->sendMessage($bot->local['en']['Start_Msg'], $bot->keyboard->getChooseLanguageKeyboard());

};

$help_closure = function($bot, $message) {

    $bot->keyboard(['text' => $bot->local[$bot->getLanguageRedisAsCache()]['Help_Msg'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};

$about_closure = function($bot, $message) {

    $bot->keyboard(['text' => $bot->local[$bot->getLanguageRedisAsCache()]['Help_Msg'], 'callback_data' => 'menu']);

    $bot->sendMessage($bot->local[$bot->language]['About_Msg'], $bot->keyboard->get());

};
