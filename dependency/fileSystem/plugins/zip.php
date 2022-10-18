<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of dependency fileSystem.
 *
 * Dependency fileSystem is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Dependency fileSystem is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with dependency fileSystem.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace dependency\fileSystem\plugins;

/**
 * Class for a file in file system
 *
 * @package Dependency/fileSystem
 */
class zip
{
    protected $executable;

    public function __construct($zipExecutable = false)
    {
        if (!$zipExecutable) {
            switch (DIRECTORY_SEPARATOR) {
                // Windows installation
                case '\\':
                    $this->executable = 'C:\Program Files (x86)\7-Zip\7z.exe';
                    break;

                case "/":
                default:
                    $this->executable = "7z";
            }
        } else {
            $this->executable = $zipExecutable;
        }
        $locale = 'fr_FR.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL=' . $locale);
    }

    public function add($archive, $filename, array $options=null)
    {
        $tokens = array('"' . $this->executable . '"');
        $tokens[] = "a";
        $tokens[] = '"' . $archive . '"';
        $tokens[] = '"' . $filename . '"';
//        $tokens[] = '-scsUTF-8';
        if ($options) {
            foreach ($options as $option) {
                $tokens[] = $option;
            }
        }

        $command = implode(' ', $tokens);
        $output = array();
        $return = null;
        $this->errors = array();

        exec($command, $output, $return);

        if ($return === 0) {
            return true;
        } else {
            $message = $this->handleError($return);
            // Output hidden for security reason (https://forge.maarch.org/issues/21936), uncomment for debug
            // throw new \dependency\fileSystem\Exception($message, $return, null, $output);
            throw new \dependency\fileSystem\Exception($message, $return, null);
        }
    }

    /**
     * Extract a compressed archive
     *
     * @param string $archive  The path of the compressed archive
     * @param string $outdir   The directory where the compressed archive is extract
     * @param string $filename The file to extract in the compressed archive
     * @param array  $options
     * @param string $command  The command to use.
     *      "e" (default value) extract files from archive (without using directory names)
     *      "x" extract files with full paths
     *
     * @return boolean
     * @throws \dependency\fileSystem\Exception
     */
    public function extract($archive, $outdir, $filename = false, array $options = null, $command = "x")
    {
        $tokens = array('"' . $this->executable . '"');
        $tokens[] = $command;
        $tokens[] = '"' . $archive . '"';
        $tokens[] = '-o"' . $outdir . '"';
        //$tokens[] = '-scsUTF-8';
        if ($filename) {
            $tokens[] = '"' . $filename . '"';
        }
        if ($options) {
            foreach ($options as $option) {
                $tokens[] = $option;
            }
        }

        $command = implode(' ', $tokens);

        $output = array();
        $return = null;

        exec($command, $output, $return);

        /*var_dump($command);
        var_dump($output);
        var_dump($return);*/

        if ($return === 0) {
            return true;
        } else {
            $message = $this->handleError($return);
            // Output hidden for security reason (https://forge.maarch.org/issues/21936), uncomment for debug
            // throw new \dependency\fileSystem\Exception($message, $return, null, $output);
            throw new \dependency\fileSystem\Exception($message, $return, null);
        }
    }

    public function contents($archive)
    {
        $tokens = array('"' . $this->executable . '"');
        $tokens[] = "l";
        $tokens[] = '"' . $archive . '"';
        $tokens[] = '-scsUTF-8';

        $command = implode(' ', $tokens);

        $output = array();
        $return = null;

        exec($command, $output, $return);

        //var_dump($command);
        //var_dump($output);
        //var_dump($return);
        $contents = array();
        for ($line = 16, $end = count($output) - 2; $line < $end; $line++) {
            $contents[] = substr($output[$line], 53);
        }

        if ($return === 0) {
            return $contents;
        } else {
            $message = $this->handleError($return);
            // Output hidden for security reason (https://forge.maarch.org/issues/21936), uncomment for debug
            // throw new \dependency\fileSystem\Exception($message, $return, null, $output);
            throw new \dependency\fileSystem\Exception($message, $return, null);
        }
    }

    public function info($archive)
    {
        $tokens = array('"' . $this->executable . '"');
        $tokens[] = "l";
        $tokens[] = '"' . $archive . '"';
        $tokens[] = "-slt";
        $tokens[] = '-scsUTF-8';

        $command = implode(' ', $tokens);

        $output = array();
        $return = null;

        exec($command, $output, $return);

        //var_dump($command);
        //var_dump($output);
        //var_dump($return);
        for ($offset = 0, $end = 12; $offset <= $end; $offset++) {
            $line = $output[$offset];
            if (strpos($line, "=") !== false) {
                $infos[trim(strtok($line, "="))] = trim(strtok("="));
            }
        }

        $infos['contents'] = array();
        for ($offset = 14, $end = count($output); $offset < $end; $offset++) {
            $line = $output[$offset];
            if ($line == "") {
                $infos['contents'][] = $content;
                continue;
            }
            if (strpos($line, "=") !== false) {
                $content[trim(strtok($line, "="))] = trim(strtok("="));
            }
        }

        if ($return === 0) {
            return $infos;
        } else {
            $message = $this->handleError($return);
            // Output hidden for security reason (https://forge.maarch.org/issues/21936), uncomment for debug
            // throw new \dependency\fileSystem\Exception($message, $return, null, $output);
            throw new \dependency\fileSystem\Exception($message, $return, null);
        }
    }

    protected function handleError($return)
    {
        switch ($return) {
            case 1:
                return "Warning: Some files could not be processed. See zip dependency for more informations.";

            case 2:
                return "Error: Unable to process the command. See zip dependency for more informations.";

            case 7:
                return "Command line error.";

            case 8:
                return "Not enough memory for operation.";

            case 255:
                return "User stopped the process.";

            default:
                return "Unknown error.";
        }
    }
}
