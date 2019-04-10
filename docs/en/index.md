Quickstart
==========

Integration of [Monolog](https://github.com/Seldaek/monolog) into Nette Framework


Installation
------------

The best way to install Kdyby/Monolog is using [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/monolog
```

and enable it in `config.neon`

```yml
extensions:
	monolog: Kdyby\Monolog\DI\MonologExtension
```


Minimal configuration
---------------------

This extension creates new configuration section `monolog`.
You have to setup only channel name and you're good to go.

```yml
monolog:
	name: awesome-blog
```

Please keep in mind that when you're using syslog, the name will be used in the logs.


Tracy integration
-----------------

The adapter replaces the default logger instance and you should not tinker with it anymore.
To put it simply: use Monolog handlers, do not change the logger anymore.

You can of course turn off the registration into Tracy

```yml
monolog:
	hookToTracy: off
```

but then you have to handle the messages yourself and you're giving up the power of Monolog.

Also, the adapter disables writing the messages to disk.
But don't worry, every message is piped to the Monolog and you can really easily send them anywhere you want - that's what handlers are for.


Fallback handler
----------------

This is the default handler that simulates the default Tracy behaviour and is registered only when no other handler is registered.
When you start registering custom handlers, it automatically disables itself (to be exact, the extension does that) because it expects you know what you're doing :)

When you wanna for example have syslog on production and still have messages written to disk on localhost, you can easily configure that

```yml
monolog:
	registerFallback: %debugMode%
```

The adapter even helps handle messages with non-standard level name (standard are: info, warning, error...).
You could have been, for example, using this in your application

```php
use Tracy\Debugger;
Debugger::log(sprintf('Sent email to "%s" with subject "%s"', $message->to, $message->subject), 'emails');
```

What does this do? It creates file `logs/emails.log` and writes the given message into it.

Because `emails` is not a standard message level name, Monolog would have failed to process it.
Therefore messages like this are sent with level `info` and the real name is set to `context.priority`, you'll see why in a moment.

Of course, when you install Monolog, this becomes obsolete, as you can start using the `Logger` object instead of static method.


Processors
----------

Processors are special type of class, that has to implement one method only - `__invoke($record)` - so they become callable.
They're called for every message, that is sent to Monolog so they can change it's contents.
When you're adding processor programatically in your application, you can also use closures.

Kdyby always registers it's custom `PriorityProcessor`, which has two tasks

- when message `context` contains `channel` name, change the real `channel` to that name and unset the value from `context`
- or, when the `context` has `priority` defined and the priority is standard name of record level (info, warning, error, ...) change the `channel` name to that

You already know about the `priority`, the other one is explained in the next chapter.


Channels and logging
--------------------

We all wanna have nice logs, right? That's what channels are for - organizing logs.
The default channel name can be set in `monolog.name`, as we've seen in the Minimal Configuration section.

The documentation of Monolog suggest using several instances of `Logger` class with different names.
This can be useful when you need complex logging setup with different handlers for different loggers.
You can still create new `Logger` service and add specific handlers, but this extension won't help you with that, it can configure only one `Logger` currently.

But you can still use custom channels for different classes!
Let's say you want to have an `EmailQueue` class that sends emails and you wanna log what emails you've sent.

Thanks to the `PriorityProcessor`, you can set the channel in `context` parameter and it will be used instead of the default channel.

```php
$this->logger->addInfo(sprintf('Sent email to "%s" with subject "%s"', $message->to, $message->subject), ['channel' => 'emails']);
```

But that's not very nice, let's use something nicer. Can we expect, that the entire class will log every time with the same channel?
Well, you certainly can if you're following the Single Responsibility Principle!

Let's look at the `EmailQueue` constructor

```php
class EmailQueue
{

	/** @var \Nette\Mail\IMailer */
	private $mailer;

	/** @var \Monolog\Logger */
	private $logger;

	public function __construct(\Nette\Mail\IMailer $mailer, \Monolog\Logger $logger)
	{
		$this->mailer = $mailer;
		$this->logger = $logger;
	}
```

As you can see, such class would have been obviously doing only one task (sending emails), so making it handle the logging itself is not a problem as it's not adding much of new code.
With bigger classes, you might wanna consider using [Kdyby/Events](https://github.com/Kdyby/Events) and moving the logger to some kind of listener.
But let's get back to channels.

Let's change the signature a bit, so the autocompletion starts working as expected

```php
public function __construct(\Nette\Mail\IMailer $mailer, \Kdyby\Monolog\Logger $logger)
```

The custom logger in Kdyby only adds one new method `->channel($name)`, which is a factory for custom Logger with custom channel, that does nothing but proxy the messages to it's parent.

If we now use the factory instead of simply assigning the value

```php
$this->logger = $logger->channel('emails');
```

every message we send to the logger will have changed the channel to `emails`, so we can simplify the `addInfo` statement from above to this


```php
$this->logger->addInfo(sprintf('Sent email to "%s" with subject "%s"', $message->to, $message->subject));
```

Much nicer, right?

Please be aware that the `->channel()` really does return new instance, don't do things like

```php
$this->logger = $logger;
$this->logger->channel('emails');
```

it won't work!

Channels in configuration
-------------------------

```neon
monolog:
	channels:
		- presenters

services:
	front.presenters.homepage:
		factory: App\Presenters\HomepagePresenter
		arguments:
			- @monolog.logger.presenters
```
