<?php

// use App\Http\Controllers\ChatController;
// use Illuminate\Support\Facades\Route;
// use Telegram\Bot\Laravel\Facades\Telegram;

// Route::get('/webhook', function () {
//     // The commandsHandler will process all /commands defined in your app
//     Telegram::commandsHandler(true);
//     return 'ok';
// });


// Route::post('/moonnewbot', function () {
//     // The commandsHandler will process all /commands defined in your app
//     Telegram::commandsHandler(true);
//     return 'ok Moon Kabir';
// });
?>
<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook', [TelegramWebhookController::class, 'handle']);


