<?php

// Include the framework
require './vendor/autoload.php';
require './src/bookmarkerbot.php';

// Create the bot
$bot = new BookmarkerBot("token");

// Create redis object
$bot->redis = new Redis();

// Connect to redis database
$bot->redis->connect('127.0.0.1');

// Load localization from directory
$bot->loadLocalization();

// Add the answers for commands
$bot->addMessageCommand("start", $start_closure);
$bot->addMessageCommand("about", $about_closure);
$bot->addMessageCommand("help", $help_closure);

// Start the bot
$bot->getUpdatesLocal();
