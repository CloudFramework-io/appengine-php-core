<?php
// https://github.com/rapidwebltd/php-google-people-api
// php composer.phar require rapidwebltd/php-google-people-api
// php vendor/rapidwebltd/php-google-oauth-2-handler/src/setup.php
// scopes:
//   https://www.googleapis.com/auth/userinfo.profile
//   https://www.googleapis.com/auth/contacts
//   https://www.googleapis.com/auth/contacts.readonly


use GuzzleHttp\Psr7\Request;

// Instagram Class v1
if (!defined ("_GooglePeole_CLASS_") ) {
    define("_GooglePeole_CLASS_", TRUE);

    class GooglePeople
    {
        var $core;
        var $error = false;
        var $errorMsg = [];
        var $people = null;

        function __construct(Core &$core)
        {
            $this->core =$core;
        }

        public function initPeople($clientId,$clientSecret,$refreshToken,$scopes) {


            $googleOAuth2Handler = new GoogleOAuth2Handler($clientId, $clientSecret, $scopes, $refreshToken);
            $this->people = new GoogleObjectPeople($googleOAuth2Handler);

        }

        public function addError($value)
        {
            $this->error = true;
            $this->errorMsg[] = $value;
        }

        public function getNewContact() {
            if(!$this->people) {
                $this->addError('mising people object. call initPeople function');
                return false;
            }
            $contact = new GoogleObjectContact($this->people);
            return($contact);
        }
    }

    class GoogleOAuth2Handler
    {
        private $clientId;
        private $clientSecret;
        private $scopes;
        private $refreshToken;
        private $client;

        public $authUrl;

        public function __construct($clientId, $clientSecret, $scopes, $refreshToken = '')
        {
            $this->clientId = $clientId;
            $this->clientSecret = $clientSecret;
            $this->scopes = $scopes;
            $this->refreshToken = $refreshToken;

            $this->setupClient();
        }

        private function setupClient()
        {
            $this->client = new \Google_Client();

            $this->client->setClientId($this->clientId);
            $this->client->setClientSecret($this->clientSecret);
            $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
            $this->client->setAccessType('offline');
            $this->client->setApprovalPrompt('force');

            foreach($this->scopes as $scope)  {
                $this->client->addScope($scope);
            }

            if ($this->refreshToken) {
                $this->client->refreshToken($this->refreshToken);
            } else {
                $this->authUrl = $this->client->createAuthUrl();
            }
        }

        public function getRefreshToken($authCode)
        {
            $this->client->authenticate($authCode);
            $accessToken = $this->client->getAccessToken();
            return $accessToken['refresh_token'];
        }

        public function performRequest($method, $url, $body = null)
        {
            $httpClient = $this->client->authorize();
            $request = new Request($method, $url, [], $body);
            $response = $httpClient->send($request);
            return $response;
        }

    }

    class GoogleObjectPeople
    {
        private $googleOAuth2Handler;

        var $PERSON_FIELDS = ['addresses', 'ageRanges', 'biographies', 'birthdays', 'braggingRights', 'coverPhotos', 'emailAddresses', 'events', 'genders', 'imClients', 'interests', 'locales', 'memberships', 'metadata', 'names', 'nicknames', 'occupations', 'organizations', 'phoneNumbers', 'photos', 'relations', 'relationshipInterests', 'relationshipStatuses', 'residences', 'skills', 'taglines', 'urls'];
        var $UPDATE_PERSON_FIELDS = ['addresses', 'biographies', 'birthdays', 'braggingRights', 'emailAddresses', 'events', 'genders', 'imClients', 'interests', 'locales', 'names', 'nicknames', 'occupations', 'organizations', 'phoneNumbers', 'relations', 'residences', 'skills', 'urls'];
        const PEOPLE_BASE_URL = 'https://people.googleapis.com/v1/';

        public function __construct(GoogleOAuth2Handler $googleOAuth2Handler)
        {
            $this->googleOAuth2Handler = $googleOAuth2Handler;
        }

        private function convertResponseConnectionToContact($connection)
        {
            $contact = new GoogleObjectContact($this);
            $contact->resourceName = $connection->resourceName;
            $contact->etag = $connection->etag;
            $contact->metadata = $connection->metadata;

            foreach($this->PERSON_FIELDS as $personField) {
                if (isset($connection->$personField)) {
                    $contact->$personField = $connection->$personField;
                } else {
                    $contact->$personField = [];
                }
            }

            return ($contact);
        }

        public function get($resourceName)
        {
            $url = self::PEOPLE_BASE_URL.$resourceName.'?personFields='.implode(',', $this->PERSON_FIELDS);

            $response = $this->googleOAuth2Handler->performRequest('GET', $url);
            $body = (string) $response->getBody();

            if ($response->getStatusCode()!=200) {
                throw new Exception($body);
            }

            $contact = json_decode($body);

            return $this->convertResponseConnectionToContact($contact);
        }

        public function all()
        {
            $url = self::PEOPLE_BASE_URL.'people/me/connections?personFields='.implode(',', $this->PERSON_FIELDS).'&pageSize=2000';

            $response = $this->googleOAuth2Handler->performRequest('GET', $url);
            $body = (string) $response->getBody();

            if ($response->getStatusCode()!=200) {
                throw new Exception($body);
            }

            $responseObj = json_decode($body);

            $contacts = [];

            foreach($responseObj->connections as $connection) {
                $contacts[] = $this->convertResponseConnectionToContact($connection);
            }

            while(isset($responseObj->nextPageToken)) {

                $url = self::PEOPLE_BASE_URL.'people/me/connections?personFields='.implode(',', $this->PERSON_FIELDS).'&pageSize=2000&pageToken='.$responseObj->nextPageToken;

                $response = $this->googleOAuth2Handler->performRequest('GET', $url);
                $body = (string) $response->getBody();

                if ($response->getStatusCode()!=200) {
                    throw new Exception($body);
                }

                $responseObj = json_decode($body);

                foreach($responseObj->connections as $connection) {
                    $contacts[] = $this->convertResponseConnectionToContact($connection);
                }
            }

            return $contacts;
        }

        public function me()
        {
            return $this->getByResourceName('people/me');
        }

        public function save(GoogleObjectContact $contact)
        {
            $requestObj = new \stdClass();

            if (isset($contact->resourceName)) {

                // If resource name exists, update the contact.
                $method = 'PATCH';
                $url = self::PEOPLE_BASE_URL.$contact->resourceName.':updateContact?updatePersonFields='.implode(',', $this->UPDATE_PERSON_FIELDS);
                $requestObj->etag = $contact->etag;
                $requestObj->metadata = $contact->metadata;

            } else {

                // If resource name does not exist, create new contact.
                $method = 'POST';
                $url = self::PEOPLE_BASE_URL.'people:createContact?parent=people/me';

            }

            foreach($this->UPDATE_PERSON_FIELDS as $personField) {
                if (isset($contact->$personField)) {
                    $requestObj->$personField = $contact->$personField;
                }
            }

            $requestBody = json_encode($requestObj);

            $response = $this->googleOAuth2Handler->performRequest($method, $url, $requestBody);
            $body = (string) $response->getBody();

            if ($response->getStatusCode()!=200) {
                throw new Exception($body);
            }

            $responseObj = json_decode($body);

            return ($this->convertResponseConnectionToContact($responseObj));
        }

        public function delete(GoogleObjectContact $contact)
        {
            $url = self::PEOPLE_BASE_URL.$contact->resourceName.':deleteContact';

            $response = $this->googleOAuth2Handler->performRequest('DELETE', $url);
            $body = (string) $response->getBody();

            if ($response->getStatusCode()!=200) {
                throw new Exception($body);
            }

            return true;
        }
    }

    class GoogleObjectContact
    {
        private $googlePeople;

        public function __construct(GoogleObjectPeople $googlePeople)
        {
            $this->googlePeople = $googlePeople;
        }

        public function save()
        {
            $updatedContact = $this->googlePeople->save($this);

            foreach($updatedContact as $key => $value) {
                $this->$key = $value;
            }

        }

        public function delete()
        {
            return $this->googlePeople->delete($this);
        }
    }
}
