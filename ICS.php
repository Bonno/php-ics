<?php

/**
 * ICS.php
 * =======
 * Use this class to create an .ics file.
 *
 * Usage
 * -----
 * Basic usage - generate ics file contents (see below for available properties):
 *   $ics = new ICS($props);
 *   $ics_file_contents = $ics->to_string();
 *
 * Setting properties after instantiation
 *   $ics = new ICS();
 *   $ics->set('summary', 'My awesome event');
 *
 * You can also set multiple properties at the same time by using an array:
 *   $ics->set(array(
 *     'dtstart' => 'now + 30 minutes',
 *     'dtend' => 'now + 1 hour'
 *   ));
 *
 * Available properties
 * --------------------
 * description
 *   String description of the event.
 * dtend
 *   A date/time stamp designating the end of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * dtstart
 *   A date/time stamp designating the start of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * location
 *   String address or description of the location of the event.
 * summary
 *   String short summary of the event - usually used as the title.
 * url
 *   A url to attach to the the event. Make sure to add the protocol (http://
 *   or https://).
 */
class ICS
{
    const DT_FORMAT = 'Ymd\THis\Z';
    const DT_FORMAT_DATE = 'Ymd';

    protected $properties = array();
    private $available_properties = array(
        'description',
        'dtend',
        'dtstart',
        'dtend_date',
        'dtstart_date',
        'location',
        'summary',
        'url'
    );

    public function __toString()
    {
        return $this->to_string();
    }

    public function __construct($props)
    {
        $this->set($props);
    }

    public function set($key, $val = false)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            if (in_array($key, $this->available_properties)) {
                $this->properties[$key] = $this->sanitize_val($val, $key);
            }
        }
    }

    public function add_event($data)
    {
        $props = array();
        // Build ICS properties - add header
        $props['BEGIN'] = 'VEVENT';

        foreach ($data as $k => $v) {
            if (in_array($k, $this->available_properties)) {
                $v = $this->sanitize_val($v, $k);
            }
            if ($k === 'description') {
                $max = 60;
                $string = $v;
                $in_pieces = array();

                while (strlen($string) > $max) {
                    $substring = substr($string, 0, $max);
                    // pull from position 0 to $substring position
                    $in_pieces[] = $substring;
                    // $string (haystack) now = haystack with out the first $substring characters
                    $string = substr($string, $max);
                }
                $in_pieces[] = $string; // final bits o' text
                $v = implode("\r\n ", $in_pieces);
            }
            if ($k === 'url') {
                $k .= ';VALUE=URI';
            }
            if ($k === 'dtstart_date') {
                $k = 'DTSTART;VALUE=DATE';
            }
            if ($k === 'dtend_date') {
                $k = 'DTEND;VALUE=DATE';
            }
            $props[strtoupper($k)] = $v;
        }

        // Set some default values
        $props['LAST-MODIFIED'] = $this->format_timestamp('now');
        $props['DTSTAMP'] = $this->format_timestamp('now');
        $props['UID'] = uniqid();
        $props['STATUS'] = 'CONFIRMED';

        $props['END'] = 'VEVENT';
        $this->events[] = $props;
    }

    protected function to_string()
    {
        $rows = $this->build_props();
        return implode("\r\n", $rows);
    }

    private function build_props()
    {
        $timeZone = new \DateTime(null, new \DateTimeZone(date_default_timezone_get()));
        // Build ICS properties - add header
        $ics_props = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Calendar//zwanger.ishetbijna.nl//NONSGML v1.0//EN',
            'X-WR-CALNAME:Zwangerschap',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-TIMEZONE:' . $timeZone->format('e'),
            'BEGIN:VTIMEZONE',
            'TZID:' . $timeZone->format('e'),
            'X-LIC-LOCATION:' . $timeZone->format('e'),
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE'
        );

        foreach ($this->events as $props) {
            // Append properties
            foreach ($props as $k => $v) {
                $ics_props[] = "$k:$v";
            }
        }

        // Build ICS properties - add footer
        $ics_props[] = 'END:VCALENDAR';

        return $ics_props;
    }

    private function sanitize_val($val, $key = false)
    {
        switch ($key) {
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
                $val = $this->format_timestamp($val, self::DT_FORMAT);
                break;
            case 'dtend_date':
            case 'dtstart_date':
                $val = $this->format_timestamp($val, self::DT_FORMAT_DATE);
                break;
            default:
                $val = $this->convert_to_utf8($this->escape_string($val));
        }

        return $val;
    }

    private function convert_to_utf8($str)
    {
        $enc = mb_detect_encoding($str);

        if ($enc && $enc != 'UTF-8') {
            return iconv($enc, 'UTF-8', $str);
        } else {
            return $str;
        }
    }

    private function format_timestamp($timestamp, $format = self::DT_FORMAT)
    {
        $dt = new \DateTime($timestamp, new \DateTimeZone(date_default_timezone_get()));
        return $dt->format($format);
    }

    private function escape_string($str)
    {
        return preg_replace('/([\,;])/', '\\\$1', $str);
    }
}
