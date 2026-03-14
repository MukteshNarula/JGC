<?php

// Load the OpenAI API key from the .env file
require 'vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Access the OpenAI API key
$openAIKey = getenv('OPENAI_API_KEY');

if (!$openAIKey) {
    throw new Exception('OpenAI API key not found.');
}

// You can now use $openAIKey to initialize your OpenAI client.
