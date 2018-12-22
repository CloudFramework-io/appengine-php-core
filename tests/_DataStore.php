<?php
class Test extends Tests
{
    function main() {
        $this->says('Init DataStore');
        $dataStore = $this->core->loadClass('DataStore',['EntityTest','SpaceNameTest',[]]);

        $this->addReturnData(['version'=>$this->core->_version]);
        $this->ends();
    }
}