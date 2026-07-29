<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramMessage;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use App\Services\RagService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Get the incoming update
            $update = Telegram::getWebhookUpdate();

            if (!$update) {
                return response('No update received', 200);
            }

            // Extract message data
            $message = $update->getMessage();

            if ($message) {
                $loadingMessage = $this->sendLoadingMessage($message);

                // Store the message in database
                $storedMessage = $this->storeMessage($update, $message);

                // Process the message and get response
                $response = $this->processMessage($message);

                $this->removeLoadingMessage($loadingMessage, $message->getChat()->getId());

                // Update the stored message with bot response
                if ($storedMessage && $response) {
                    $storedMessage->update([
                        'bot_response' => $response,
                        'is_processed' => true,
                    ]);
                }

                // Send response back to user (if needed)
                if ($response) {
                    Telegram::sendMessage([
                        'chat_id' => $message->getChat()->getId(),
                        'text' => $response,
                    ]);
                }
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            \Log::error('Telegram webhook error: ' . $e->getMessage());
            return response('Error: ' . $e->getMessage(), 500);
        }
    }

    private function storeMessage($update, $message)
    {
        try {
            $chat = $message->getChat();
            $from = $message->getFrom();

            return TelegramMessage::firstOrCreate(
                ['message_id' => $message->getMessageId()],    
                [
                    'user_id' => $from->getId(),
                    'username' => $from->getUsername(),
                    'first_name' => $from->getFirstName(),
                    'last_name' => $from->getLastName(),
                    'message_id' => $message->getMessageId(),
                    'message_text' => $message->getText(),
                    'chat_id' => $chat->getId(),
                    'chat_type' => $chat->getType(),
                    'raw_data' => $update->toArray(),
                    'is_processed' => false,
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Failed to store message: ' . $e->getMessage());
            return null;
        }
    }

    private function processMessage($message)
    {
        $text = $message->getText();
        $chatId = $message->getChat()->getId();
        $userId = $message->getFrom()->getId();

        // Handle commands
        if ($text && str_starts_with($text, '/')) {
            return $this->handleCommand($text, $chatId, $userId);
        }

        // Handle regular messages
        if ($text) {
            return $this->handleRegularMessage($text, $chatId, $userId);
        }

        // Handle non-text messages (photos, documents, etc.)
        if ($message->getPhoto()) {
            return "I received your photo! 📸";
        }

        return null;
    }

    private function sendLoadingMessage($message)
    {
        $text = $message->getText();

        if (!$text || str_starts_with($text, '/')) {
            return null;
        }

        try {
            return Telegram::sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Preparing a response...',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to send Telegram loading message: '.$e->getMessage());

            return null;
        }
    }

    private function removeLoadingMessage($loadingMessage, $chatId)
    {
        if (!$loadingMessage) {
            return;
        }

        try {
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $loadingMessage->getMessageId(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to remove Telegram loading message: '.$e->getMessage());
        }
    }

    private function handleCommand($text, $chatId, $userId)
    {
        return match($text) {
            '/start' => "Welcome to the bot! 👋\nYour messages will be stored in our database.\nTry sending any text!",
            '/help' => "Available commands:\n/start - Start the bot\n/help - Show this help\n/messages - Show your message count",
            '/messages' => $this->getMessageCount($userId),
            default => "Unknown command. Type /help for available commands.",
        };
    }

    private function handleRegularMessage($text, $chatId, $userId)
    {
        try {
            // Greetings/basic chat → OpenAI; other questions → uploaded documents (RAG).
            return app(RagService::class)->answer($text);
        } catch (\Throwable $e) {
            \Log::error('RAG answer failed: '.$e->getMessage());
            return "Sorry, I couldn't process your question right now.";
        }
    }

    private function getMessageCount($userId)
    {
        $count = TelegramMessage::where('user_id', $userId)->count();
        return "You have sent $count message(s) to this bot! 📊";
    }
}
