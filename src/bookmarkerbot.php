<?php

// Define bot state
define("MENU", 0);
define("GET_NAME", 1);
define("GET_DESC", 2);
define("GET_URL", 3);
define("GET_HASHTAG", 4);


// The bot class
class BookmarkerBot extends DanySpin97\PhpBotFramework\Bot {

    // Add the function for processing messages
    protected function processMessage($message) {

        // What are we expecting to receive
        switch($this->getStatus()) {

            case GET_URL:

                // If the user sent a valid url in the message
                if (isset($message['entities']) && $message['entities'][0]['type'] === 'url') {

                    // Get the url from the message
                    $url = $this->remove_http(substr($message['text'], $message['entites'][0]['offset'], $message['entities'][0]['length']));

                    // Save the url
                    $redis->set($this->chat_id . ':bookmark', 'url', $url);

                    // Edit the message before this
                    $this->editMessageText($this->redis->get($this->chat_id . ':message_id'), $this->local[$this->language]['Url_Msg'] . $url);

                    // Say the user to insert the name for this bookmark
                    $new_message_id = $this->sendMessage($this->local[$this->language]['SendName_Msg'], $this->keyboard->getBackKeyboard())['message_id'];

                    // Update the message id
                    $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                    // Change status
                    $this->setStatus(GET_NAME);

                // We didn't got a valid message
                } else {

                    // Say the user to resend it
                    $new_message_id = $this->sendMessage($this->local[$this->language]['UrlNotValid_Msg'], $this->keyboard->getBackKeyboard());

                    // Update the message id
                    $this->redis->set($this->chat_id . ':message_id', $new_message_id);

                }

                break;

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

                    // Save hashtags
                    $redis->set($this->chat_id . ':hashtags', $hashtags);

                    $sth = $bot->pdo->prepare('INSERT INTO Bookmark (name, description, url, user_id) VALUES (:name, :description, :url, :user_id)');
                    $bookmark = $bot->redis->get($bot->getChatID() . 'bookmark');

                    $sth->bindParam(':name', $bookmark['name']);
                    $sth->bindParam(':description', $bookmark['description']);
                    $sth->bindParam(':url', $bookmark['url']);
                    $sth->bindParam(':user_id', $bot->getChatID());

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

    public function remove_http($url) {

        $disallowed = array('http://', 'https://');

        foreach($disallowed as $d) {

            if(strpos($url, $d) === 0) {

                return str_replace($d, '', $url);

            }

        }

        return $url;

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
