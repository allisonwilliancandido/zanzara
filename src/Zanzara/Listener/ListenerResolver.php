<?php

declare(strict_types=1);

namespace Zanzara\Listener;

use Symfony\Contracts\Cache\CacheInterface;
use Zanzara\Telegram\Type\CallbackQuery;
use Zanzara\Telegram\Type\Message;
use Zanzara\Telegram\Type\Update;

/**
 * Resolves the listeners collected in ListenerCollector accordingly to Telegram Update type.
 *
 * @see ListenerCollector
 */
abstract class ListenerResolver extends ListenerCollector
{

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @param Update $update
     * @return Listener[]
     */
    protected function resolve(Update $update): array
    {
        $listeners = [];
        $updateType = $update->getUpdateType();

        switch ($updateType) {

            case Message::class:
                $text = $update->getMessage()->getText();
                if ($text) {
                    $listener = $this->findAndPush($listeners, 'messages', $text);
                    //todo maybe some problem with the regex handler in the future
                    if ($listener) {
                        //clean the state because a listener has been found
                        $userId = $update->getEffectiveChat()->getId();
                        $this->cache->deleteItem(strval($userId));
                    } else {
                        //there is no listener so we look for the state
                        $userId = $update->getEffectiveChat()->getId();
                        $handler = $this->cache->getItem(strval($userId))->get();
                        if ($handler) {
                            // wrap the handler function as listener and push it in the array
                            $listeners[] = new Listener($handler, $text);
                        }
                    }
                }
                break;

            case CallbackQuery::class:
                $text = $update->getCallbackQuery()->getMessage()->getText();
                $this->findAndPush($listeners, 'cb_query_texts', $text);
                break;
        }

        $this->merge($listeners, $updateType);
        $this->merge($listeners, Update::class);

        return $listeners;
    }

    /**
     * @param Listener[] $listeners
     * @param string $listenerType
     * @param string $listenerId
     * @return Listener|null
     */
    private function findAndPush(array &$listeners, string $listenerType, string $listenerId): ?Listener
    {
        if (isset($this->listeners[$listenerType])) {
            foreach ($this->listeners[$listenerType] as $regex => $listener) {
                if (preg_match($regex, $listenerId)) {
                    $listeners[] = $listener;
                    return $listener;
                }
            }
        }
        return null;
    }

    /**
     * @param Listener[] $listeners
     * @param string $listenerType
     */
    private function merge(array &$listeners, string $listenerType)
    {
        $toMerge = $this->listeners[$listenerType] ?? null;
        if ($toMerge) {
            $listeners = array_merge($listeners, $toMerge);
        }
    }

}
