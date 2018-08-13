<?php
/**
 * Created by PhpStorm.
 * User: Rick
 * Date: 13/08/2018
 * Time: 15:41
 */

namespace WildPHP\Modules\AntiSpam;


use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Channels\ChannelCollection;
use WildPHP\Core\Commands\Command;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\Commands\ParameterStrategy;
use WildPHP\Core\Commands\StringParameter;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\IRCMessages\PRIVMSG;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\DataStorage\DataStorageFactory;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Logger\Logger;
use WildPHP\Core\Modules\BaseModule;
use WildPHP\Core\Users\User;

/**
 * @property array silenced
 */
class AntiSpam extends BaseModule
{
    protected $blacklistPatterns = [];
    protected $exempts = [];
    protected $silenced = [];

    /**
     * BaseModule constructor.
     *
     * @param ComponentContainer $container
     */
    public function __construct(ComponentContainer $container)
    {
        $this->setContainer($container);

        CommandHandler::fromContainer($container)->registerCommand('blacklist', new Command(
            [$this, 'blacklistCommand'],
            [
                new ParameterStrategy(1, -1, [
                    'pattern' => new StringParameter()
                ], true)
            ],
            new CommandHelp([
                'Blacklists a given pattern. The pattern must be given as a regular expression.',
                'Usage: blacklist [pattern]'
            ]),
            'as_blacklist'
        ));

        CommandHandler::fromContainer($container)->registerCommand('unblacklist', new Command(
            [$this, 'unblacklistCommand'],
            [
                new ParameterStrategy(1, -1, [
                    'pattern' => new StringParameter()
                ], true)
            ],
            new CommandHelp([
                'Removes a pattern from the blacklist.',
                'Usage: unblacklist [pattern]'
            ]),
            'as_blacklist'
        ));

        CommandHandler::fromContainer($container)->registerCommand('exempt', new Command(
            [$this, 'exemptCommand'],
            [
                new ParameterStrategy(1, 1, [
                    'nickname' => new StringParameter()
                ], true)
            ],
            new CommandHelp([
                'Exempt a given user to prevent their messages from being flagged.',
                'Usage: exempt [nickname]'
            ]),
            'as_exempt'
        ));

        CommandHandler::fromContainer($container)->registerCommand('unexempt', new Command(
            [$this, 'unexemptCommand'],
            [
                new ParameterStrategy(1, 1, [
                    'nickname' => new StringParameter()
                ], true)
            ],
            new CommandHelp([
                'Remove an exempt for a user.',
                'Usage: unexempt [nickname]'
            ]),
            'as_exempt'
        ));

        // we use first instead of a regular on here because other modules might have been initialized before this one
        // which would cause them to process the message before we do
        EventEmitter::fromContainer($this->getContainer())->first('irc.line.in.privmsg', [$this, 'validateMessage']);

        $storage = DataStorageFactory::getStorage('antispam');
        $this->blacklistPatterns = (array) $storage->get('blacklistPatterns') ?? [];
        $this->exempts = (array) $storage->get('exempts') ?? [];
    }

    /**
     * @param Channel $source
     * @param User $user
     * @param $args
     * @param ComponentContainer $container
     */
    public function blacklistCommand(Channel $source, User $user, $args, ComponentContainer $container)
    {
        $pattern = $args['pattern'];

        if (@preg_match('/' . $pattern . '/', null) === false)
        {
            $message = 'This is not a valid regex pattern.';
            Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
            return;
        }

        if (in_array($pattern, $this->blacklistPatterns))
        {
            $message = 'This pattern is already blacklisted.';
            Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
            return;
        }
        $storage = DataStorageFactory::getStorage('antispam');

        $this->blacklistPatterns[] = $pattern;
        $storage->set('blacklistPatterns', $this->blacklistPatterns);

        $message = 'Successfully blacklisted pattern.';
        Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
        return;
    }

    /**
     * @param Channel $source
     * @param User $user
     * @param $args
     * @param ComponentContainer $container
     */
    public function unblacklistCommand(Channel $source, User $user, $args, ComponentContainer $container)
    {
        $pattern = $args['pattern'];

        if (!in_array($pattern, $this->blacklistPatterns))
        {
            $message = 'This pattern is not blacklisted.';
            Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
            return;
        }
        $storage = DataStorageFactory::getStorage('antispam');

        unset($this->blacklistPatterns[array_search($pattern, $this->blacklistPatterns)]);
        $storage->set('blacklistPatterns', $this->blacklistPatterns);

        $message = 'Successfully removed this pattern from the blacklist.';
        Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
        return;
    }

        /**
         * @param Channel $source
         * @param User $user
         * @param $args
         * @param ComponentContainer $container
         */
    public function exemptCommand(Channel $source, User $user, $args, ComponentContainer $container)
    {
        $nickname = $args['nickname'];

        if (in_array($nickname, $this->exempts))
        {
            $message = 'This nickname is already exempted.';
            Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
            return;
        }

        $storage = DataStorageFactory::getStorage('antispam');

        $this->exempts[] = $nickname;
        $storage->set('exempts', $this->exempts);

        $message = 'Successfully exempted nickname.';
        Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
        return;
    }

    /**
     * @param Channel $source
     * @param User $user
     * @param $args
     * @param ComponentContainer $container
     */
    public function unexemptCommand(Channel $source, User $user, $args, ComponentContainer $container)
    {
        $nickname = $args['nickname'];

        if (!in_array($nickname, $this->exempts))
        {
            $message = 'This nickname is not exempted.';
            Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
            return;
        }

        $storage = DataStorageFactory::getStorage('antispam');

        unset($this->exempts[array_search($nickname, $this->exempts)]);
        $storage->set('exempts', $this->exempts);

        $message = 'Successfully removed nickname from the exempts list.';
        Queue::fromContainer($this->getContainer())->privmsg($source->getName(), $message);
        return;
    }

    /**
     * @param PRIVMSG $incomingIrcMessage
     * @param Queue $queue
     * @throws \Yoshi2889\Container\NotFoundException
     */
    public function validateMessage(PRIVMSG $incomingIrcMessage, Queue $queue)
    {
        if (empty($this->blacklistPatterns) || in_array($incomingIrcMessage->getNickname(), $this->exempts))
            return;

        if (array_key_exists($incomingIrcMessage->getChannel(), $this->silenced) &&
            in_array($incomingIrcMessage->getNickname(), $this->silenced[$incomingIrcMessage->getChannel()]))
            return;

        $channel = ChannelCollection::fromContainer($this->getContainer())->findByChannelName($incomingIrcMessage->getChannel());

        // a nonexisting channel can happen during early initialisation or when someone PRIVMSGs the bot itself.
        if (!$channel)
            return;

        $text = $incomingIrcMessage->getMessage();

        foreach ($this->blacklistPatterns as $pattern)
        {
            if ($pattern == false)
                continue;

            if (preg_match('/' . $pattern . '/', $text) === 1)
            {
                Logger::fromContainer($this->getContainer())->debug('Kicking user because spam detected', [
                    'nickname' => $incomingIrcMessage->getNickname(),
                    'message' => $incomingIrcMessage->getMessage(),
                    'matchedPattern' => $pattern
                ]);

                $botUser = $channel->getUserCollection()->findByNickname(Configuration::fromContainer($this->getContainer())['currentNickname']);
                if (!$channel->getChannelModes()->isUserInMode('o', $botUser))
                {
                    $queue->privmsg($incomingIrcMessage->getChannel(),
                        'Spam detected but unable to kick user; please report to channel OPs. Silencing further warnings for user ' . $incomingIrcMessage->getNickname() . ' in this channel.');

                    $this->silenced[$incomingIrcMessage->getChannel()][] = $incomingIrcMessage->getNickname();
                    return;
                }

                $queue->kick($incomingIrcMessage->getChannel(), $incomingIrcMessage->getNickname(), 'Spam detected');
                $incomingIrcMessage->_isSpam = true;

                // wipe the message contents so other modules can not interact with it
                $incomingIrcMessage->setMessage('');
                return;
            }
        }
    }

    /**
     * @return string
     */
    public static function getSupportedVersionConstraint(): string
    {
        return '^3.0.0';
    }
}