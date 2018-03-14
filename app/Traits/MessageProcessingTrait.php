<?php

namespace App\Traits;

trait MessageProcessingTrait
{
    /**
     * Process the actual message and apply patterns.
     *
     * @param string $actualMessage
     * @return string
     */
    public function processResponseMessage($actualMessage)
    {
        $messagePatterns = config('messages.messagePatterns');

        foreach ($messagePatterns ?? [] as $pattern) {
            if ($this->matchesPattern($pattern, $actualMessage)) {
                return $this->modifyMessage($pattern, $actualMessage);
            }
        }

        return $actualMessage;
    }

    /**
     * Check if the actual message matches a given pattern.
     *
     * @param array $pattern
     * @param string $actualMessage
     * @return bool
     */
    private function matchesPattern(array $pattern, $actualMessage)
    {
        $type = $pattern['type'];
        $matches = $pattern['matches'];

        foreach ($matches as $match) {
            if ($type === 'partial' && stripos($actualMessage, $match) !== false) {
                return true;
            } elseif ($type === 'exact' && strcasecmp($actualMessage, $match) === 0) {
                return true;
            } elseif ($type === 'regex' && preg_match($match, $actualMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Modify the message based on the pattern.
     *
     * @param array $pattern
     * @param string $actualMessage
     * @return string
     */
    private function modifyMessage(array $pattern, $actualMessage)
    {
        $modifiedMessage = $pattern['modifiedMessage'];

        if ($pattern['type'] === 'regex' && preg_match($pattern['matches'][0], $actualMessage, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $modifiedMessage = str_replace('{' . $key . '}', $value, $modifiedMessage);
                }
            }
        }

        return $modifiedMessage;
    }
}
