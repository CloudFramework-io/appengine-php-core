<?php
// Instagram Class v1
if (!defined ("_SmartDate_CLASS_") ) {
    define("_SmartDate_CLASS_", TRUE);

    class SmartDate
    {
        private $core;
        var $date = null;
        private $format = 'Y-m-d';

        function __construct(Core &$core, $config)
        {
            $this->core = $core;
        }

        public function get($format = null)
        {
            if (!$format) $format = $this->format;
            return (date($format));
        }

        /**
         * Return an array of Dates
         * @param int $init
         * @param int $end
         * @param string $type
         * @param null $format
         * @return array
         */
        public function getArray($init = -1, $end = 0, $type = 'day', $format = null)
        {
            if(!in_array($type, ['day', 'month', 'year'])) return ['wrong type. Only supported day,month,year'];
            if (!$format ) $format = $this->format;

            $ret = [];
            $inc = ($end > $init) ? 1 : -1;

            $date = new DateTime();
            $date->modify("$init {$type}");
            $ret[] = $date->format($format);
            for ($i = $init; $i != $end; $i += $inc) {
                $date->modify("$inc {$type}");
                $ret[] = $date->format($format);
            }
            return ($ret);
        }

        /**
         * Return an array of dates calling getArray where the date is the key and filling it with $value
         * @param int $value
         * @param int $init
         * @param int $end
         * @param string $type
         * @param null $format
         * @return array
         */
        public function getArrayInKeys($value = 0, $init = -1, $end = 0, $type = 'day', $format = null)
        {
            $dates = $this->getArray($init, $end, $type, $format);
            $ret = [];
            if (is_array($dates)) foreach ($dates as $date) {
                $ret[$date] = $value;
            }
            return $ret;
        }
    }
}