<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Bus;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Simple synchronous event dispatcher.
 *
 * Implements PSR-14 EventDispatcherInterface. Dispatches events to registered
 * listeners in priority order. Supports stoppable events via the
 * StoppableEventInterface — propagation stops once an event is marked stopped.
 *
 * This is an in-memory sync dispatcher suitable for development and
 * moderate-scale deployments. For high-traffic scenarios, consider
 * swapping to a message queue-backed dispatcher (Symfony Messenger, RabbitMQ).
 */
class SimpleEventDispatcher implements EventDispatcherInterface
{
    /**
     * Registered listeners grouped by event class and priority.
     *
     * @var array<string, array<int, list<callable>>>
     */
    private array $listeners = [];

    /**
     * Cached sorted listeners for each event class.
     *
     * @var array<string, list<callable>>
     */
    private array $sortedListeners = [];

    /**
     * Dispatch an event to all registered listeners.
     *
     * Listeners are called in descending priority order. If the event
     * implements StoppableEventInterface and propagation is stopped,
     * remaining listeners are skipped.
     *
     * @param object $event The event to dispatch
     * @return object The event after all listeners have been called
     */
    public function dispatch(object $event): object
    {
        $eventClass = get_class($event);

        // Check if it's a stoppable event already stopped
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        $listeners = $this->getListeners($eventClass);
        foreach ($listeners as $listener) {
            $listener($event);

            // Respect stoppable events
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }

    /**
     * Register a listener for a specific event class.
     *
     * @param string   $eventClass The fully qualified event class name
     * @param callable $listener   The listener callable
     * @param int      $priority   Higher priority = called first (default: 0)
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
        unset($this->sortedListeners[$eventClass]);
    }

    /**
     * Remove a previously registered listener.
     *
     * @param string   $eventClass The event class the listener was registered for
     * @param callable $listener   The listener to remove
     */
    public function removeListener(string $eventClass, callable $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $priority => &$listeners) {
            $key = array_search($listener, $listeners, true);
            if ($key !== false) {
                unset($listeners[$key]);
                if ($listeners === []) {
                    unset($this->listeners[$eventClass][$priority]);
                }
                unset($this->sortedListeners[$eventClass]);
                break;
            }
        }
    }

    /**
     * Get all listeners for a given event class, sorted by priority.
     *
     * @param string $eventClass The event class name
     * @return list<callable> Sorted listeners
     */
    private function getListeners(string $eventClass): array
    {
        if (!isset($this->sortedListeners[$eventClass])) {
            $this->sortedListeners[$eventClass] = $this->sortListeners($eventClass);
        }

        return $this->sortedListeners[$eventClass];
    }

    /**
     * Sort listeners by descending priority, then by registration order.
     *
     * @param string $eventClass The event class name
     * @return list<callable> Listeners sorted by priority descending
     */
    private function sortListeners(string $eventClass): array
    {
        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        // Sort by priority descending (higher priority = called first)
        krsort($this->listeners[$eventClass]);

        // Flatten the priority groups into a single list
        $sorted = [];
        foreach ($this->listeners[$eventClass] as $listeners) {
            array_push($sorted, ...$listeners);
        }

        return $sorted;
    }
}