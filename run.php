<?php

// Include the framework
require './vendor/autoload.php';
require './src/bookmarkerbot.php';
require './src/data.php';

// Create the bot
$bot = new BookmarkerBot($token);

// Create redis object
$bot->redis = new Redis();

// Connect to redis database
$bot->redis->connect('127.0.0.1');

$bot->pdo = new PDO("pgsql:host=localhost;port=5432;dbname=$database_name;user=$user;password=$password");

// Load localization from directory
$bot->loadLocalization();

// Add the answers for commands
$bot->addMessageCommand("start", $start_closure);
$bot->addMessageCommand("about", $about_msg_closure);
$bot->addMessageCommand("help", $help_msg_closure);
$bot->addMessageCommand("delete_bookmarks", $delete_bookmarks_warning_closure);
$bot->addCallbackCommand("menu", $menu_closure);
$bot->addCallbackCommand("browse", $browse_closure);
$bot->addCallbackCommand("channel", $channel_closure);
$bot->addCallbackCommand("help", $help_cbq_closure);
$bot->addCallbackCommand("about", $about_cbq_closure);
$bot->addCallbackCommand("language", $language_closure);
$bot->addCallbackCommand("skip", $skip_closure);
$bot->addCallbackCommand("back", $back_closure);
$bot->addCallbackCommand("deletechannel", $delete_channel_closure);
$bot->addCallbackCommand("changechannel", $change_channel_closure);
$bot->addCallbackCommand("deletebookmark", $delete_bookmarks_closure);

// Start the bot
$bot->getUpdatesLocal();
