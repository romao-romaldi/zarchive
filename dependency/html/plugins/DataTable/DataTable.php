<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of dependency html.
 *
 * Dependency html is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Dependency html is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with dependency html.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace dependency\html\plugins\DataTable;

class DataTable extends \dependency\html\AbstractHtmlClass
{
    /* -------------------------------------------------------------------------
    - Properties
    ------------------------------------------------------------------------- */
    protected $parameters;

    protected $columnFilter;

    protected $exportOptions;

    protected $toolbars;

    protected $catalog;

    /* -------------------------------------------------------------------------
    - Methods
    ------------------------------------------------------------------------- */
    public function __construct($element)
    {
        parent::__construct($element);
        $this->parameters = new \StdClass();
        $this->exportOptions = new \StdClass();

        // init sDom parameters
        $this->parameters->sDom = '<"dataTable-footer clearfix"<"pull-left"f>><"table-responsive"t><"dataTable-footer"<"pull-left"li><"pull-right"p><"clearfix">>';

        $element->addHtmlClass("dataTable");

        // $this->element->ownerDocument->addScript('/public/js/dataTables_1.10.5/dataTables.min.js');
        $this->element->ownerDocument->addScript('/public/js/DataTables/datatables.js');

        $this->parameters->sPaginationType = "full_numbers";

        $view = $this->element->ownerDocument;
        $catalogElement = $view->XPath->query("ancestor-or-self::*[@data-translate-catalog]", $this->element)->item(0);
        if ($catalogElement) {
            $this->catalog = $catalogElement->getAttribute('data-translate-catalog');
        }
        
        $all = $view->translator->getText("All", false, $this->catalog);

        $this->parameters->lengthMenu = [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, $all]
        ];
    }

    public function saveHtml()
    {
        $dataTableId = \laabs\uniqid();
        $this->element->setAttribute('data-table-id', $dataTableId);
        $parameters = json_encode($this->parameters);

        $scriptText = <<<EOS
var dataTableObjects = {};
$(document).ready(function() {
dataTableObjects['$dataTableId'] = $('*[data-table-id="$dataTableId"]').DataTable($parameters)
EOS;

        if ($this->columnFilter) {
            $columnFilterParams = json_encode($this->columnFilter);
            $scriptText .= <<<EOS
.columnFilter($columnFilterParams)
EOS;
        }

        $scriptText .= ";
                        });";

        if (!empty($this->toolbars)) {
            foreach ($this->toolbars as $class => $html) {
                $scriptText .= <<<EOS
$('div.$class').html('$html');
EOS;
            }
        }

        $scriptText .= <<<EOS
$('[title]').tooltip();
EOS;

        $scriptText .=<<<EOS
$('[title]').tooltip();
EOS;

        $script = $this->element->ownerDocument->createElement('script');
        $CdataSection = $this->element->ownerDocument->createCDataSection($scriptText);
        $script->appendChild($CdataSection);

        $this->element->appendChild($script);
    }

    public function translate()
    {
        $translator = $this->element->ownerDocument->translator;

        $this->parameters->oLanguage = new \stdClass();

        $this->parameters->oLanguage->sProcessing   = $translator->getText("Processing...", false, $this->catalog);
        $this->parameters->oLanguage->sSearch       = $translator->getText("Search:", false, $this->catalog);

        $this->parameters->oLanguage->sLengthMenu   = $translator->getText("Show _MENU_ entries", false, $this->catalog);
        $this->parameters->oLanguage->sInfo         = $translator->getText("Showing _START_ to _END_ of _TOTAL_ entries", false, $this->catalog);
        $this->parameters->oLanguage->sInfoEmpty    = $translator->getText("Showing 0 to 0 of 0 entries", false, $this->catalog);
        $this->parameters->oLanguage->sInfoFiltered = $translator->getText("(filtered from _MAX_ total entries)", false, $this->catalog);
        $this->parameters->oLanguage->sInfoPostFix  = ""; //$translator->getText("", false, $this->catalog);
        $this->parameters->oLanguage->sLoadingRecords = $translator->getText("Loading...", false, $this->catalog);
        $this->parameters->oLanguage->sZeroRecords  = $translator->getText("No matching records found", false, $this->catalog);
        $this->parameters->oLanguage->sEmptyTable   = $translator->getText("No data available in table", false, $this->catalog);

        $this->parameters->oLanguage->oPaginate = new \stdClass();
        $this->parameters->oLanguage->oPaginate->sFirst   = $translator->getText("First", false, $this->catalog);
        $this->parameters->oLanguage->oPaginate->sPrevious= $translator->getText("Previous", false, $this->catalog);
        $this->parameters->oLanguage->oPaginate->sNext    = $translator->getText("Next", false, $this->catalog);
        $this->parameters->oLanguage->oPaginate->sLast    = $translator->getText("Last", false, $this->catalog);

        $this->parameters->oLanguage->oAria = new \stdClass();
        $this->parameters->oLanguage->oAria->sSortAscending      = $translator->getText(": activate to sort column ascending", false, $this->catalog);
        $this->parameters->oLanguage->oAria->sSortDescending    = $translator->getText(": activate to sort column descending", false, $this->catalog);
    }

    public function setPaginationType($paginationType)
    {
        $this->parameters->sPaginationType = $paginationType;
    }

    public function setLengthMenu(array $lengths, array $lengthLabels = null)
    {
        if (is_null($lengthLabels)) {
            $this->parameters->lengthMenu = $lengths;
        } else {
            $this->parameters->lengthMenu = [
                $lengths,
                $lengthLabels
            ];
        }
    }

    public function setUnsearchableColumns($indexes)
    {
        // Two ways to pass the list of columns : arg1 is an array of indexes or each arg is an index
        $args = func_get_args();
        if (!is_array($args[0])) {
            $indexes = $args;
        }

        $object = new \StdClass();
        $object->bSearchable = false;
        $object->aTargets = $indexes;
        if (!isset($this->parameters->aoColumnDefs)) {
            $this->parameters->aoColumnDefs = array();
        }

        for ($i=0, $j=count($this->parameters->aoColumnDefs); $i<$j; $i++) {
            if (isset($this->parameters->aoColumnDefs[$i]->bSearchable)) {
                $object->aTargets = array_merge($this->parameters->aoColumnDefs[$i]->aTargets, $indexes);
                $this->parameters->aoColumnDefs[$i] = $object;

                return;
            }
        }

        $this->parameters->aoColumnDefs[] = $object;
    }

    public function setUnsortableColumns($indexes)
    {
        $args = func_get_args();
        if (!is_array($args[0])) {
            $indexes = $args;
        }

        $object = new \StdClass();
        $object->bSortable = false;
        $object->aTargets = $indexes;
        if (!isset($this->parameters->aoColumnDefs)) {
            $this->parameters->aoColumnDefs = array();
        }
        for ($i=0, $j=count($this->parameters->aoColumnDefs); $i<$j; $i++) {
            if (isset($this->parameters->aoColumnDefs[$i]->bSortable)) {
                $object->aTargets = array_merge($this->parameters->aoColumnDefs[$i]->aTargets, $indexes);
                $this->parameters->aoColumnDefs[$i] = $object;

                return;
            }
        }
        $this->parameters->aoColumnDefs[] = $object;
    }

    public function setSortingColumn($index, $sortingBy)
    {
        if (!isset($this->parameters->aoColumnDefs)) {
            $this->parameters->aoColumnDefs = array();
        }

        $object = new \StdClass();
        $object->iDataSort = (int)$sortingBy;
        $object->aTargets = array((int)$index);

        $this->parameters->aoColumnDefs[] = $object;
    }

    public function setColumnType($type, $indexes)
    {
        if (!is_array($indexes)) {
            $indexes = array($indexes);
        }

        if (!isset($this->parameters->columnDefs)) {
            $this->parameters->columnDefs = array();
        }

        $object = new \StdClass();
        $object->type = $type;
        $object->aTargets = $indexes;

        $this->parameters->columnDefs[] = $object;
    }

    public function setUnvisibleColumns($indexes)
    {
        $args = func_get_args();
        if (!is_array($args[0])) {
            $indexes = $args;
        }

        $object = new \StdClass();
        $object->bVisible = false;
        $object->aTargets = $indexes;

        if (!isset($this->parameters->aoColumnDefs)) {
            $this->parameters->aoColumnDefs = array();
        }
        for ($i=0, $j=count($this->parameters->aoColumnDefs); $i<$j; $i++) {
            if (isset($this->parameters->aoColumnDefs[$i]->bVisible)) {
                $object->aTargets = array_merge($this->parameters->aoColumnDefs[$i]->aTargets, $indexes);
                $this->parameters->aoColumnDefs[$i] = $object;

                return;
            }
        }
        $this->parameters->aoColumnDefs[] = $object;
    }

    public function enableColumnFilter($placeHolder = false)
    {
        $this->columnFilter = new \stdClass();
        if ($placeHolder) {
            $this->columnFilter->sPlaceHolder = $placeHolder;
        }

        $this->element->ownerDocument->addScript('/public/js/dataTables_1.10.5/dataTables.columnFilter.js');
    }

    public function setColumnFilter($index, $type, $values = array())
    {
        if (!isset($this->columnFilter)) {
            $this->enableColumnFilter();
        }

        if (!isset($this->columnFilter->aoColumns)) {
            $this->columnFilter->aoColumns = array();
        }

        $columnFilter = new \stdClass();
        // text, number, select, date-range, number-range
        $columnFilter->type = $type;

        if ($type == "select") {
            $columnFilter->values = array_values($values);
        }

        for ($i=0, $l=$index; $i<$l; $i++) {
            if (!isset($this->columnFilter->aoColumns[$i])) {
                $this->columnFilter->aoColumns[$i] = null;
            }
        }

        $this->columnFilter->aoColumns[(int) $index] = $columnFilter;
    }

    public function setSorting($sorting)
    {
        $this->parameters->aaSorting = $sorting;
    }

    public function setNbRows($nb)
    {
        $this->parameters->iDisplayLength = $nb;
    }

    public function setDomStyle($domStyle)
    {
        $this->parameters->sDom = $domStyle;
    }

    public function setStateSave($val)
    {
        $this->parameters->bStateSave = $val;
    }

    public function setLanguageFile($langfile)
    {
        if (!isset($this->parameters->oLanguage)) {
            $this->parameters->oLanguage = new \StdClass();
        }

        $this->parameters->oLanguage->sUrl = $langfile;
    }

    /**
     * Add a custom toolbar
     * @param string The toolbar html
     *
     *
     */
    public function addCustomToolbar($class, $html)
    {
        $this->toolbars[$class] = $html;
    }

    protected function serializeDomStyle($arr = false, $dom = false)
    {
        if (!$arr) {
            $arr = $this->domStyle;
        }
        foreach ($arr as $item) {
            $dom .= "<";
        }
    }

    /**
     * Add possibility to export datatable resuls in different formats
     *
     * @param array   $exportType       array containing button objects to set
     * @param boolean $onlyExportButton Either returning dom only consists of export button or full dataTable display
     *
     */
    public function setExport($exportType = array(), $onlyExportButton = false)
    {
        if (!is_array($exportType) || empty($exportType)) {
            $exportType = [
                [
                    "exportType" => "csv",
                    "text" => "export csv"
                ],
                [
                    "exportType" => "excel",
                    "text" => "export xls"
                ],
                [
                    "exportType" => "pdf",
                    "text" => "export pdf"
                ],
                [
                    "exportType" => "copy",
                    "text" => "copy to clipboard"
                ],
                [
                    "exportType" => "print",
                    "text" => "print table"
                ]
            ];
        }

        // dom is a datatable parameter allowing to control displayed elements of table
        // t referers to the table
        // l referers to the length changing input control
        // f referers to the filtering input
        // i refers to the information summary
        // p refers to the pagination control
        // r refers to the processing of display element
        // see https://datatables.net/reference/option/dom for more detailed informations
        $this->parameters->dom = "
            <'dataTable-footer clearfix'
                <'col-sm-6 no_padding'f>
                <'col-sm-6 no_padding'
                    <'pull-right' B>
                >
            >
            tr
            <'dataTable-footer clearfix'
                <'col-sm-6 no_padding'l>
                <'col-sm-6 no_padding'
                    <'pull-right' p>
                >
                i
            >";

        if ($onlyExportButton) {
            $this->parameters->dom = "<<'no_padding' B>>";
        }

        $this->parameters->buttons = [];

        foreach ($exportType as $exportButton) {
            $button = new \stdClass();
            $button->extend = $exportButton['exportType'];
            $button->text = $exportButton['text'];
            if (!empty($this->exportOptions)) {
                $button->exportOptions = $this->exportOptions;
            }
            $button->titleAttr = 'Export to '. $exportButton['exportType'];
            if (isset($exportButton['titleAttr'])) {
                $button->titleAttr = $exportButton['titleAttr'];
            }
            $button->orientation = 'landscape';
            $button->className = 'btn btn-default btn-sm';
            array_push($this->parameters->buttons, $button);
        }
    }

    /**
     * Add possibility to set which columns are exported
     *
     * @param array   $columns       array containing the columns number to export in file
     *
     */
    public function setColumnsToExport($columns)
    {
        if (!is_array($columns) || empty($columns)) {
            return;
        }
        $this->exportOptions->columns = $columns;
    }
}
