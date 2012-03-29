<?php defined('SYSPATH') OR die('No direct script access.');

// Add 422 response code
Response::$messages[422] = 'Unprocessable Entity';

// Initialize Airbrake for error handling
Airbrake::init();