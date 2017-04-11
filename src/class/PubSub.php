<?php

use Google\Cloud\PubSub\PubSubClient;

class PubSub
{

    private $core = null;
    /** @var $client PubSubClient|null  */
    var $client = null;
    /** @var $topic \Google\Cloud\PubSub\Topic|null  */
    var $topic = null;
    /** @var $subscription \Google\Cloud\PubSub\Subscription|null  */
    var $subscription = null;
    var $error = false;
    var $errorMsg = null;

    /**
     * DataSQL constructor.
     * @param Core $core
     */
    function __construct(Core &$core)
    {

        // Get core function
        $this->core = $core;
        $projectId = $this->core->config->get('GoogleProjectId');
        if(!$projectId) return($this->addError('Missing GoogleProjectId config var'));


        require_once $this->core->system->root_path . '/vendor/autoload.php';
        try {
            if($this->core->is->development()) {
                $projectId = 'localhost';
                // gcloud beta emulators pubsub start
                putenv('PUBSUB_EMULATOR_HOST=http://localhost:8826');
                $this->client = new PubSubClient([
                    'projectId' => $projectId
                ]);
            } else {
                $this->client = new PubSubClient([
                    'projectId' => $projectId
                ]);
            }
        } catch (Exception $e) {
            $this->addError($e->getCode().': '.$e->getMessage());
        }

    }

    /**
     * @param $topic
     * @return \Google\Cloud\PubSub\Topic|null
     */
    public function getTopic($topic) {
        if(!is_object($this->client)) return($this->addError('missing pubsub client'));

        $this->topic = $this->client->topic($topic);
        try {
            $this->topic->info();
            return $this->topic;
        } catch(Exception $e) {
            try {
                $this->topic = $this->client->createTopic($topic);
                return $this->topic;
            } catch(Exception $e) {
                $this->addError($e->getCode().': '.$e->getMessage());
            }
        }
        return null;
    }

    /**
     * @param $subscription
     * @param $topic
     * @return \Google\Cloud\PubSub\Subscription|null
     */
    public function getSubscription($subscription,$topic) {
        try {
            $this->subscription = $this->client->subscription($subscription.$topic);
            if(!$this->subscription->exists()) {
                $this->subscription = $this->client->subscribe($subscription.$topic,$topic);
            }
            return $this->subscription;
        } catch(Exception $e) {
            $this->addError($e->getCode().': '.$e->getMessage());
        }
        return null;
    }

    /**
     * @param $subscription
     * @param $topic
     * @return \Google\Cloud\PubSub\Subscription|null
     */
    public function getSubscriptionMessages($subscription=null) {
        if(!is_object($subscription)) $subscription = $this->subscription;

        try {
            $messages = $subscription->pull();
            /** @var $message \Google\Cloud\PubSub\Message */
            if(is_object($messages))
                foreach ($messages as $message) {

                    $ret[] = $message->info();
                }
            return $ret;

        } catch(Exception $e) {
            $this->addError($e->getCode().': '.$e->getMessage());
        }
        return null;
    }


    public function publish($message,$data=[],$topic=null) {
        if(!is_object($topic)) $topic = &$this->topic;
        try {
            $message_ids = $topic->publish(['data'=>$message,'attributes'=>$data]);
            return($message_ids);
        } catch (Exception $e) {
            $this->addError($e->getCode().': '.$e->getMessage());
        }
    }

    public function getSubscriptions() {
        $ret = [];
        $subscriptions = $this->client->subscriptions();
        if(is_array($subscriptions))
        foreach ($subscriptions as $subscription) {
            $ret[] = $subscription->info();
        }
        return $ret;
    }


    /**
     * Add Error message
     * @param $err
     */
    private function addError($err) {
        $this->error = true;
        $this->errorMsg[] = $err;
    }



}