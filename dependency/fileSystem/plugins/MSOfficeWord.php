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
 * Class for Microsoft Word conversion functions
 *
 * @package Dependency/fileSystem
 * @author  Arnaud PAUGET <arnaud.pauget@maarch.org>
 */
class MSOfficeWord implements \dependency\fileSystem\conversionInterface
{
    protected $MSOconvertExecutable;

    public function __construct($MSOconvertExecutable)
    {
        $this->MSOconvertExecutable = $MSOconvertExecutable;
    }

    public function convert($srcfile, $options = null)
    {
        $outputFile = dirname($srcfile) . DIRECTORY_SEPARATOR . pathinfo($srcfile, PATHINFO_FILENAME) . "." . $options["extension"];

        $tokens = array('powershell.exe -command ". \'"' . $this->MSOconvertExecutable . '"\'; ');
        $tokens[] = "convertFileToPdf";
        $tokens[] = "'".str_replace("'", "''", $srcfile)."'";
        $tokens[] = "'".str_replace("'", "''", $outputFile)."'";
        $tokens[] = 'word"';

        $command = implode(' ', $tokens);

        $output = array();
        $return = null;
        $this->errors = array();

        exec($command, $return, $output);

        $convertedfile = dirname($srcfile) . DIRECTORY_SEPARATOR . pathinfo($srcfile, PATHINFO_FILENAME) . "." . $options["extension"];

        if (file_exists($convertedfile)) {
            return $convertedfile;
        }

        throw new \dependency\fileSystem\Exception("error during conversion $command", $return, null, $output);
    }

    public function getSoftwareName()
    {
        return "Microsoft Word";
    }

    public function getSoftwareVersion()
    {
        if (DIRECTORY_SEPARATOR == "/") {
            $tokens = array('"'.$this->libreOfficeExecutable.'"');
            $tokens[] = "--version";

            $command = implode(' ', $tokens);

            $output = array();
            $return = null;
            $this->errors = array();

            exec($command, $output, $return);

            if ($return === 0) {
                return explode('', $output[0])[1];
            } else {
                throw new \dependency\fileSystem\Exception("error during the recuperation of the software version", $return, null, $output);
            }

        } else {
            return "4.4.2.0";
            /*$command = 'wmic datafile where Name="'.$this->libreOfficeExecutable.'" get Version';

            $output = array();
            $return = null;
            $this->errors = array();

            exec($command, $output, $return);
            
            if (is_array($output)) {
                return $output[1];
            } else {
                throw new \dependency\fileSystem\Exception("error during the recuperation of the software version", $return, null, $output);
            }*/
        }

        
    }
}
