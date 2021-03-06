<?php
/**
 * Part of the Joomla Framework Symfony Event Dispatcher Bridge
 *
 * @copyright  Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\SymfonyEventDispatcherBridge\Symfony;

use Joomla\Event\DispatcherInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\SymfonyEventDispatcherBridge\Joomla\Event as JoomlaBridgeEvent;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Bridge class decorating a Symfony EventDispatcherInterface implementation with the Joomla DispatcherInterface
 *
 * @since  __DEPLOY_VERSION__
 */
class EventDispatcher implements DispatcherInterface
{
	/**
	 * The decorated dispatcher.
	 *
	 * @var    EventDispatcherInterface
	 * @since  __DEPLOY_VERSION__
	 */
	private $dispatcher;

	/**
	 * A container holding wrapped event subscribers
	 *
	 * @var    EventSubscriberInterface[]
	 * @since  __DEPLOY_VERSION__
	 */
	private $wrappedSubscribers = [];

	/**
	 * Constructor.
	 *
	 * @param   EventDispatcherInterface  $dispatcher  The decorated dispatcher.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(EventDispatcherInterface $dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Attaches a listener to an event
	 *
	 * @param   string    $eventName  The event to listen to.
	 * @param   callable  $callback   A callable function
	 * @param   integer   $priority   The priority at which the $callback executed
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addListener(string $eventName, callable $callback, int $priority = 0): bool
	{
		$this->dispatcher->addListener($eventName, $callback, $priority);

		return true;
	}

	/**
	 * Adds an event subscriber.
	 *
	 * @param   SubscriberInterface  $subscriber  The subscriber.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addSubscriber(SubscriberInterface $subscriber)
	{
		if ($subscriber instanceof EventSubscriberInterface)
		{
			$this->dispatcher->addSubscriber($subscriber);

			return;
		}

		$this->dispatcher->addSubscriber($this->getWrappedSubscriber($subscriber));
	}

	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param   string          $name   The name of the event to dispatch.
	 * @param   EventInterface  $event  The event to pass to the event handlers/listeners.
	 *
	 * @return  EventInterface  The event after being passed through all listeners.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function dispatch(string $name, EventInterface $event = null): EventInterface
	{
		if ($event instanceof SymfonyEvent)
		{
			$this->dispatcher->dispatch($name, $event);

			return $event;
		}

		if ($event instanceof Event)
		{
			$this->dispatcher->dispatch($name, $event->getEvent());

			return $event;
		}

		$decoratingEvent = new JoomlaBridgeEvent($event);

		$this->dispatcher->dispatch($name, $decoratingEvent);

		return $event;
	}

	/**
	 * Get the listeners registered to the given event.
	 *
	 * @param   string  $event  The event to fetch listeners for
	 *
	 * @return  callable[]  An array of registered listeners sorted according to their priorities.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getListeners($event)
	{
		return $this->dispatcher->getListeners($event);
	}

	/**
	 * Tell if the given listener has been added.
	 *
	 * If an event is specified, it will tell if the listener is registered for that event.
	 *
	 * @param   callable  $callback   The callable to check is listening to the event.
	 * @param   string    $eventName  The event to check a listener is subscribed to.
	 *
	 * @return  boolean  True if the listener is registered, false otherwise.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function hasListener(callable $callback, $eventName = null)
	{
		if (!$this->dispatcher->hasListeners($eventName))
		{
			return false;
		}

		$listeners = $this->dispatcher->getListeners($eventName);

		if ($eventName === null)
		{
			foreach ($listeners as $sortedListeners)
			{
				if (array_search($callback, $sortedListeners, true) !== false)
				{
					return true;
				}
			}

			return false;
		}

		return array_search($callback, $listeners, true) !== false;
	}

	/**
	 * Removes an event listener from the specified event.
	 *
	 * @param   string    $eventName  The event to remove a listener from.
	 * @param   callable  $listener   The listener to remove.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function removeListener(string $eventName, callable $listener)
	{
		$this->dispatcher->removeListener($eventName, $listener);
	}

	/**
	 * Removes an event subscriber.
	 *
	 * @param   SubscriberInterface  $subscriber  The subscriber.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function removeSubscriber(SubscriberInterface $subscriber)
	{
		if ($subscriber instanceof EventSubscriberInterface)
		{
			$this->dispatcher->removeSubscriber($subscriber);

			return;
		}

		$this->dispatcher->removeSubscriber($this->getWrappedSubscriber($subscriber));
	}

	/**
	 * Create a wrapped event subscriber to proxy the Joomla implementation to the Symfony implementation
	 *
	 * @param   SubscriberInterface  $subscriber  The subscriber to wrap.
	 *
	 * @return  EventSubscriberInterface
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function getWrappedSubscriber(SubscriberInterface $subscriber): EventSubscriberInterface
	{
		$hash = spl_object_hash($subscriber);

		if (isset($this->wrappedSubscribers[$hash]))
		{
			return $this->wrappedSubscribers[$hash];
		}

		$wrappedSubscriber = new class($subscriber) implements EventSubscriberInterface
		{
			/**
			 * The subscriber being decorated.
			 *
			 * @var    SubscriberInterface
			 * @since  __DEPLOY_VERSION__
			 */
			private static $subscriber;

			/**
			 * Decorating subscriber constructor.
			 *
			 * @param   SubscriberInterface  $subscriber  The subscriber being decorated.
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function __construct(SubscriberInterface $subscriber)
			{
				self::$subscriber = $subscriber;
			}

			/**
			 * Magic method to proxy subscriber method calls.
			 *
			 * @param   string  $name       The method on the subscriber to call.
			 * @param   array   $arguments  The arguments to pass to the subscriber.
			 *
			 * @return  mixed   The filtered input value.
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public function __call($name, $arguments)
			{
				if (self::$subscriber === null)
				{
					throw new \RuntimeException('The wrapped subscriber was not correctly initialised');
				}

				if (!method_exists(self::$subscriber, $name))
				{
					throw new \BadMethodCallException(
						sprintf('Call to undefined method %1$s on decorated dispatcher %2$s', $name, \get_class(self::$subscriber))
					);
				}

				self::$subscriber->$name(...$arguments);
			}

			/**
			 * Returns an array of event names this subscriber wants to listen to.
			 *
			 * @return  array
			 *
			 * @since   __DEPLOY_VERSION__
			 */
			public static function getSubscribedEvents()
			{
				if (self::$subscriber === null)
				{
					throw new \RuntimeException('The wrapped subscriber was not correctly initialised');
				}

				$subscribedEvents = [];

				foreach (self::$subscriber->getSubscribedEvents() as $eventName => $params)
				{
					if (\is_array($params))
					{
						$subscribedEvents[$eventName][] = [$listener[0], $listener[1] ?? Priority::NORMAL];
					}
					else
					{
						$subscribedEvents[$eventName][] = $params;
					}
				}

				return $subscribedEvents;
			}
		};

		return $this->wrappedSubscribers[$hash] = $wrappedSubscriber;
	}
}
