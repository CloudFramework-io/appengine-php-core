<?php
class API extends RESTful
{
    function main()
    {
        /** @var PubSub $pubsub */
        $pubsub = $this->core->loadClass('PubSub');
        if($pubsub->error) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));

        // Create a Topic
        if(!($topic = $pubsub->getTopic('testtopic'))) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));

        // Create a Subscription over a topic
        if(!($subscription = $pubsub->getSubscription('testsubscription','testtopic'))) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));

        // Send a message in a topic
        if(!($message_id = $pubsub->publish('Test message '.uniqid('pubsub'),['fieldTest'=>'FieldValue'],$topic))) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));

        //$this->addReturnData([$topic->info(),$pubsub->getSubscriptions(),$pubsub->getSubscriptionMessages($subscription)]);
        $this->addReturnData([$topic->info(),$subscription->info(),$pubsub->getSubscriptions(),$message_id]);

    }
}