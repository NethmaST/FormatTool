
<?php

class SRSParser {
    public function parse($text) {
        $lines = explode("\n", $text);
        $requirements = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (!empty($line)) {
                $requirements[] = $line;
            }
        }

        return $requirements;
    }
}

?>