<?php

/*
start - Start me!
delete_bookmarks - Delete all your bookmarks
help - Get help using me
about - Know more about me and my creator
 */

/** The bot class */
class BookmarkerBot extends PhpBotFramework\Bot
{
    /**
     *  Remove url prefix
     */
    public function removeHttp($url)
    {
        $disallowed = array('http://', 'https://');

        foreach($disallowed as $d) {
            if(strpos($url, $d) === 0) {
                return str_replace($d, '', $url);
            }
        }

        return $url;
    }

    // Get a bookmark from database passing a bookmark id
    public function getBookmark(int $bookmark_id) {

        // Get the bookmark from the database
        $sth = $this->database->pdo->prepare("SELECT id, name, url, description, message_id FROM Bookmark WHERE id = :bookmark_id");
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Get bookmark from result
        $this->bookmark = $sth->fetch();

        $sth = null;

        $this->getHashtags($bookmark_id);

    }

    public function getHashtags(int $bookmark_id) {

        // Get all tags related to the bookmark
        $sth = $this->database->pdo->prepare("SELECT tag_id FROM Bookmark_Tag WHERE bookmark_id = :bookmark_id");
        $sth->bindParam(':bookmark_id', $bookmark_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Initialize an empty array for storing hashtags
        $this->hashtags = [];

        // Prepare the query to get the id of each tag related to the bookmark
        $sth2 = $this->database->pdo->prepare('SELECT name FROM Tag WHERE id = :tag_id');
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
                $this->hashtags []= "#$hashtag";

            }

        }

        // Close statement
        $sth = null;
        $sth2 = null;

    }

    /**
     *  Update the message in the channel
     */
    public function updateBookmarkMessage() {
        // Get channel id
        $channel_id = $this->getChannelID();

        // Check if it is valid and the bookmark has been sent in the channel
        if ($channel_id == false || !isset($this->bookmark['message_id'])) {
            return;
        }

        $this->useChatId(
            $channel_id,
            function () {
                // Add keyboard on the message
                $this->keyboard->addButton($this->local->getStr('Share_Button'), 'switch_inline_query', $this->bookmark['name']);
                $this->keyboard->addButton($this->local->getStr('Edit_Button'), 'callback_data', 'id_' . $this->bookmark['id']);

                // Reformat the bookmark and update it
                $this->editMessageText($this->bookmark['message_id'], $this->formatBookmark(), $this->keyboard->get());
            }
        );
    }

    // Format a bookmark
    static public function formatBookmarkStatic($bookmark, $hashtags, $preview = true) : string
    {

        // Add the name of the bookmark with bold formattation
        $message = '<b>' . $bookmark['name'] . '</b>' . NEW_LINE;

        // Add description formatted in italics
        ($bookmark['description'] !== 'NULL') ? ($message .= '<i>' . $bookmark['description'] . '</i>' . NEW_LINE) : null;

        // Add the url and the hashtags based if it is not in preview
        $message .= $preview ? ('<a href="' . $bookmark['url'] . '">Link</a>') :
            (BookmarkerBot::formatHashtags($hashtags)
            . NEW_LINE . $bookmark['url']);

        return $message;

    }

    // Wrapper between the instantiated object and the static function
    public function formatBookmark($preview = false) : string {

        $bookmark = $this->bookmark;

        $hashtags = $this->hashtags;

        return $this->formatBookmarkStatic($bookmark, $hashtags, $preview);

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

        if (!empty($hashtags)) {
            $message .= NEW_LINE;
        }

        return $message;
    }

    static public function formatItem($item, &$keyboard) : string {

        $message = BookmarkerBot::formatBookmarkStatic($item, []) . NEW_LINE;

        $keyboard->addButton($item['name'], 'callback_data', 'id_' . $item['id']);

        return $message;

    }

    // Get the channel id of the current user
    public function getChannelID() {

        $sth = $this->database->pdo->prepare('SELECT channel_id FROM TelegramUser WHERE chat_id = :chat_id');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $channel_id = ($sth->fetch())['channel_id'];

        $sth = null;

        // If it is not valid
        if ($channel_id == 0) {

            //return 0
            return 0;

        }

        // If the id is an username
        if (!is_integer($channel_id)) {

            // return the username plus the @
            return '@' . $channel_id;
        }

        // Else the id is numeric and belongs to a private channel,
        // append private channel prefix and return the value
        return '-100' . $channel_id;

    }

    // Send a bookmark in the channel of the user
    public function sendBookmarkChannel() {

        // If the bookmark has no id (for unknown reasons)
        if (!isset($this->bookmark['id'])) {

            // Exit the function
            return;

        }

        // Get channel id
        $channel_id = $this->getChannelID();

        // Check if the user has a channel and the query didn't get errors
        if ($channel_id == false) {

            return;

        }

        $this->useChatId(
            $channel_id,
            function () {
                // Add a button that will let user edit the bookmark
                $this->keyboard->addLevelButtons(['text' => $this->local->getStr('Edit_Button'), 'callback_data' => 'editbkch_' . $this->bookmark['id']]);

                // Send the message to the channel
                $new_message_id = ($this->sendMessage($this->formatBookmark(), $this->keyboard->get()))['message_id'];

                // Add the message id to the bookmark
                $sth = $this->database->pdo->prepare('UPDATE Bookmark SET message_id = :message_id WHERE id = :bookmark_id');

                $sth->bindParam(':message_id', $new_message_id);
                $sth->bindParam(':bookmark_id', $this->bookmark['id']);

                try {

                    $sth->execute();

                } catch (PDOException $e) {

                    echo $e->getMessage();

                }

                $sth = null;
            }
        );
    }

    // Save a bookmark in the database
    public function saveBookmark() : int
    {
        // Create bookmark
        $sth = $this->database->pdo->prepare('INSERT INTO Bookmark (name, description, url, user_id) VALUES (:name, :description, :url, :user_id) RETURNING id');
        $this->bookmark = $this->redis->hGetAll($this->chat_id . ':bookmark');

        $sth->bindParam(':name', $this->bookmark['name']);
        $sth->bindParam(':description', $this->bookmark['description']);
        $sth->bindParam(':url', $this->bookmark['url']);

        $sth->bindParam(':user_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            $e->getMessage();

        }

        // Get id of the bookmark created
        $this->bookmark['id'] = $sth->fetch()['id'];

        // Close connection
        $sth = null;

        // Save hashtags
        $this->saveHashtags();

        // Send the new bookmark to the channel
        $this->sendBookmarkChannel();

        if ($this->bookmark['id'] !== false) {

            return $this->bookmark['id'];

        }

        return 0;
    }

    // Save hashtags on database
    public function saveHashtags() {

        // Is there a tag with a specific name?
        $sth = $this->database->pdo->prepare('SELECT id FROM Tag WHERE name = :name LIMIT 1');

        // Create a tag
        $sth2 = $this->database->pdo->prepare('INSERT INTO Tag (name) VALUES (:name) RETURNING id');

        // Tag a bookmark passing bookmard and tag ids
        $sth3 = $this->database->pdo->prepare('INSERT INTO Bookmark_Tag (bookmark_id, tag_id) VALUES (:bookmark_id, :tag_id)');

        // For each hashtag added to the bookmark
        foreach ($this->hashtags as $index => $hashtag) {

            $hashtag = mb_substr($hashtag, 1);

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
            $sth3->bindParam(':bookmark_id', $this->bookmark['id']);

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

    // Get the menu message to show to the user and add the keyboard
    public function menuMessage() : string {

        // Add menu buttons
        $this->keyboard->addButton($this->local->getStr('Browse_Button'), 'callback_data', 'browse');
        $this->keyboard->changeRow();

        if ($this->getChannelID() != false) {

            $this->keyboard->addButton($this->local->getStr('EditChannel_Button'), 'callback_data', 'channel');

        } else {

            $this->keyboard->addButton($this->local->getStr('AddChannel_Button'), 'callback_data', 'channel');

        }

        $this->keyboard->changeRow();

        $this->keyboard->addButton($this->local->getStr('Language_Button'), 'callback_data', 'language');

        $this->keyboard->addLevelButtons(['text' => $this->local->getStr('Help_Button'), 'callback_data' => 'help'], ['text' => $this->local->getStr('About_Button'), 'callback_data' => 'about']);

        return $this->local->getStr('Menu_Msg');

    }

    // Add the keyboard for editing bookmarks as a side effect
    public function addEditBookmarkKeyboard() {

        // Add a button to open the bookmark url
        $this->keyboard->addButton($this->local->getStr('Link_Button'), 'url', $this->bookmark['url']);

        $this->keyboard->changeRow();

        // Add button for editing bookmark's url and name
        $this->keyboard->addButton($this->local->getStr('EditUrl_Button'), 'callback_data', 'edit_url_' . $this->bookmark['id']);
        $this->keyboard->addButton($this->local->getStr('EditName_Button'), 'callback_data', 'edit_name_' . $this->bookmark['id']);

        // Change keyboard row
        $this->keyboard->changeRow();

        // Does the bookmark has a description?
        if ($this->bookmark['description'] !== 'NULL') {

            // Add a button for editing the description
            $this->keyboard->addButton($this->local->getStr('EditDescription_Button'), 'callback_data', 'edit_desc_' . $this->bookmark['id']);

        } else {

            // Add a button to add the description
            $this->keyboard->addButton($this->local->getStr('AddDescription_Button'), 'callback_data', 'add_desc_' . $this->bookmark['id']);

        }

        // Does the bookmark has hashtags?
        if (!empty($this->hashtags)) {

            // Add a button to edit hashtags
            $this->keyboard->addButton($this->local->getStr('EditHashtags_Button'), 'callback_data', 'edit_hashtags_' . $this->bookmark['id']);

        } else {

            // Add a button to add hashtags
            $this->keyboard->addButton($this->local->getStr('AddHashtags_Button'), 'callback_data', 'add_hashtags_' . $this->bookmark['id']);

        }

        $this->keyboard->changeRow();

        // Add a button to add hashtags
        $this->keyboard->addButton($this->local->getStr('DeleteBookmark_Button'), 'callback_data', 'deletebookmark_' . $this->bookmark['id']);

        // Change keyboard row
        $this->keyboard->changeRow();

        // Add a button to share the bookmark
        $this->keyboard->addButton($this->local->getStr('Share_Button'), 'switch_inline_query', $this->bookmark['name']);

        $this->keyboard->changeRow();

        // Add a back button if the user was browsing the bookmarks
        if ($this->redis->exists($this->chat_id . ':index')) {

            $this->keyboard->addButton($this->local->getStr('Back_Button'), 'callback_data', 'list/' . $this->redis->get($this->chat_id . ':index'));

        }

        // Add a button to go to the menu
        $this->keyboard->addButton($this->local->getStr('Menu_Button'), 'callback_data', 'menu');

    }

    // Get channel data to send them to the user
    public function getChannelData($channel_id) : string {

        // Initialize empty string
        $message = '';

        // Get channel title by calling getChat api method
        $channel = $this->getChat($channel_id);

        // Add channel title
        $message .= $channel['title'] . NEW_LINE;

        // How many bookmark has been sent in the channel
        $bookmark_channel = $this->getBookmarkCount();

        // Append the count
        $message .= $this->local->getStr('BookmarkCount_Msg') . $bookmark_channel . NEW_LINE;

        // Add explanation on how channel works
        $message .= NEW_LINE . $this->local->getStr('ChannelExplanation_Msg');

        // Get how many bookmark has been sent in the channel
        $sth = $this->database->pdo->prepare('SELECT COUNT(id) FROM Bookmark WHERE user_id = :chat_id');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $bookmark_count = $sth->fetchColumn();

        $sth = null;

        // Add buttons for editing and deleting channel
        $this->keyboard->addButton($this->local->getStr('ChangeChannel_Button'), 'callback_data', 'changechannel');
        $this->keyboard->addButton($this->local->getStr('DeleteChannel_Button'), 'callback_data', 'deletechannel');

        $this->keyboard->changeRow();

        // Add button to go to the menu
        $this->keyboard->addButton($this->local->getStr('Menu_Button'), 'callback_data', 'menu');

        return $message;

    }

    // Get how many bookmarks does a user have
    public function getBookmarkCount() : int {

        // Use a prepared statement
        $sth = $this->database->pdo->prepare('SELECT COUNT(message_id) FROM Bookmark WHERE user_id = :user_id AND message_id != 0');

        $sth->bindParam(':user_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $count = $sth->fetchColumn();

        // If the result is valid
        if ($count !== false) {

            return $count;

        }

        return 0;

    }

    public function sendAllBookmarkChannel() {

        // Get all bookmark that has not been sent in channel
        $sth = $this->database->pdo->prepare('SELECT id, name, message_id, url, description FROM Bookmark WHERE user_id = :chat_id AND message_id = \'0\'');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Iterate over all results
        while ($row = $sth->fetch()) {

            // The bookmark to process is the current row
            $this->bookmark = $row;

            // Get the bookmark with this id
            $this->getHashtags($row['id']);

            // Send it to the channel
            $this->sendBookmarkChannel();

        }

        $sth = null;

    }

    public function deleteAllBookmarkChannel() {

        $channel_id = $this->getChannelID();

        if ($channel_id === 0) {

            return;

        }

        // Get all bookmark that has been sent in channel
        $sth = $this->database->pdo->prepare('SELECT message_id FROM Bookmark WHERE user_id = :chat_id AND message_id != \'0\'');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $this->useChatId(
            $channel_id,
            function () use ($sth) {
                // Iterate over all results
                while ($row = $sth->fetch()) {

                    // Say that the bookmark has been deleted
                    $this->editMessageText($row['message_id'], $this->local->getStr('DeletedBookmarkChannel_Msg'));

                }
                $sth = null;
            }
        );

        // Delete all message_id in bookmarks
        $sth = $this->database->pdo->prepare('UPDATE Bookmark SET message_id = 0 WHERE user_id = :chat_id');

        $sth->bindParam(':chat_id', $this->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        $sth = null;

    }

    public function updateBookmarkChannel() {

        // Get all bookmark that haven't been sent in the channel
        $sth = $this->database->pdo->prepare('SELECT * FROM Bookmark WHERE message_id = 0 AND user_id = :chat_id');

        $chat_id = $this->chat_id;
        $sth->bindParam(':chat_id', $chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        // Iterate over all of them
        while ($row = $sth->fetch()) {

            // Set this bookmark as the one that we are iteratign
            $this->bookmark = $row;

            // Get hashtags from database associated with the current bookmark
            $this->getHashtags($row['id']);

            // Send it in the channel
            $this->sendBookmarkChannel();

        }

        // Close connection
        $sth = null;

    }

};

