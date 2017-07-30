<?php

require_once 'defines.php';

/**
 * Process inline queries
 */

$inline_query = function ($bot, $inline_query) {

    $bot->local->getLanguageRedis();

    // Get data
    $text = $inline_query->getQuery();

    // Is the user registred in the database?
    $sth = $bot->database->pdo->prepare('SELECT COUNT(chat_id) FROM TelegramUser WHERE chat_id = :chat_id');

    $chat_id = $bot->chat_id;
    $sth->bindParam(':chat_id', $chat_id);
    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    $user_exists = $sth->fetchColumn();

    if ($user_exists == 0) {

        // If the user hasn't started the bot yet, but a button "Register"
        $bot->answerInlineQuery("", $bot->local->getStr('Register_InlineQuery'));

        return;

    }

    $sth = null;

    $bot->results = new PhpBotFramework\Entities\InlineQueryResults();

    // If the query is empty
    if (!isset($text) || $text === '') {

        // Get all user bookmarks
        $sth = $bot->database->pdo->prepare("SELECT * FROM Bookmark WHERE user_id = :chat_id");

        $sth->bindParam(':chat_id', $bot->chat_id);

        try {

            $sth->execute();

        } catch (PDOException $e) {

            echo $e->getMessage();

        }

        while ($bookmark = $sth->fetch()) {

            $bot->bookmark = $bookmark;

            $bot->getHashtags($bookmark['id']);

            // Get message to show
            $message = $bot->formatBookmark(false);

            // Add a link button
            $bot->keyboard->addButton($bot->local->getStr('Link_Button'), 'url', $bot->bookmark['url']);

            // Add a button with the link
            $bot->results->newArticle($bot->bookmark['name'], $message, $bot->formatHashtags($bot->hashtags), $bot->keyboard->getArray(), 'HTML', true);

        }

        $sth = null;

        $bot->answerInlineQuery($bot->results->get(), $bot->local->getStr('Menu_Button'), true, 40);

        return;

    }

    // Set lowercase query
    $text = mb_strtolower($text);

    // If the user is specifically searching for a hashtag
    if ($text[0] === '#') {

        // Remove hashtag from query
        $text = mb_substr($text, 1);

        // Search id of the bookmark that has similar hashtag
        $sth = $bot->database->pdo->prepare("SELECT DISTINCT Bookmark_Tag.bookmark_id FROM Bookmark_Tag INNER JOIN Tag ON Bookmark_Tag.tag_id = Tag.id WHERE LOWER(Tag.name) LIKE :text LIMIT 50");

        // The user is searching in both name, url and hashtag
    } else {

        $sth = $bot->database->pdo->prepare("SELECT id AS bookmark_id FROM Bookmark WHERE LOWER(name) LIKE :text OR url LIKE :text
            UNION
            SELECT DISTINCT Bookmark_Tag.bookmark_id FROM Bookmark_Tag INNER JOIN Tag ON Bookmark_Tag.tag_id = Tag.id WHERE LOWER(Tag.name) LIKE :text LIMIT 50");

    }

    // Add wildcard to query string so it will match any name
    $text = "%$text%";

    $sth->bindParam(':text', $text);

    try {

        $sth->execute();

    } catch (PDOException $e) {

        echo $e->getMessage();

    }

    while ($bookmark = $sth->fetch()) {

        $bot->getBookmark($bookmark['bookmark_id']);

        // Create the message to send if the user choose that article
        $message = $bot->formatBookmark(false);

        // Add a button with the link
        $bot->keyboard->addButton($bot->local->getStr('Link_Button'), 'url', $bot->bookmark['url']);

        // Create the article
        $bot->results->newArticle($bot->bookmark['name'], $message, $bot->formatHashtags($bot->hashtags), $bot->keyboard->getArray(), 'HTML', true);

    }

    $sth = null;

    $bot->answerInlineQuery($bot->results->get(), $bot->local->getStr('Menu_Button'), true, 40);

};
