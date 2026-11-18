<?php

return [

    'markup_percent' => 25,

    'rates' => [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ],

    'characters_per_text_token' => 4,

    'text_token_variance_percent' => 15,

    'tokens_per_reference_image' => 765,

    'output_tokens' => [
        'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
        'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
        'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
    ],

    'max_reasoning_tokens' => [
        'low' => 500,
        'medium' => 2000,
        'high' => 8000,
    ],

];
