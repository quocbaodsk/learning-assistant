<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Third Party Services
  |--------------------------------------------------------------------------
  |
  | This file is for storing the credentials for third party services such
  | as Mailgun, Postmark, AWS and more. This file provides the de facto
  | location for this type of information, allowing packages to have
  | a conventional file to locate the various service credentials.
  |
  */

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses'      => [
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'resend'   => [
    'key' => env('RESEND_KEY'),
  ],

  'slack'    => [
    'notifications' => [
      'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
      'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
  ],

  // chatgpt ai
  'openai'   => [
    'key'         => env('OPENAI_API_KEY'),
    'url'         => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
    'model'       => env('OPENAI_API_MODEL', 'gpt-4.1-2025-04-14'),
    'temperature' => env('OPENAI_API_TEMPERATURE', 0.7),
    'max_tokens'  => env('OPENAI_API_MAX_TOKENS', 1000),
  ],

  // chatgpt ai
  'github'   => [
    'key'         => env('GITHUB_API_KEY'),
    'url'         => env('GITHUB_API_URL', 'https://models.github.ai/inference/chat/completions'),
    'model'       => env('GITHUB_API_MODEL', 'openai/gpt-4o'),
    'temperature' => env('GITHUB_API_TEMPERATURE', 0.7),
    'max_tokens'  => env('GITHUB_API_MAX_TOKENS', 1000),
  ],

  // deepseek ai
  'deepseek' => [
    'key'         => env('DEEPSEEK_API_KEY'),
    'url'         => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/chat/completions'),
    'model'       => env('DEEPSEEK_API_MODEL', 'deepseek-chat'),
    'temperature' => env('DEEPSEEK_API_TEMPERATURE', 0.7),
    'max_tokens'  => env('DEEPSEEK_API_MAX_TOKENS', 1000),
  ],

  // claude ai
  'claude'   => [
    'key'         => env('CLAUDE_API_KEY'),
    'url'         => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/chat/completions'),
    'model'       => env('CLAUDE_API_MODEL', 'claude-3-7-sonnet-20250219'),
    'temperature' => env('CLAUDE_API_TEMPERATURE', 0.7),
    'max_tokens'  => env('CLAUDE_API_MAX_TOKENS', 1000),
  ],
];
