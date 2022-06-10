function ConvertFileToPdf {

[CmdletBinding()]

param
(
  
[Parameter (Mandatory=$true,Position=0)]
[String]
$SourceFile,

[Parameter (Mandatory=$true,Position=1)]
[String]
$DestinationFile,

[Parameter (Mandatory=$true,Position=2)]
[String]
$application

)
    switch ( $application )
    {
        "word" { ConvertWordTo-PDF $SourceFile $DestinationFile }
        "excel" { ConvertExcelTo-PDF $SourceFile $DestinationFile }
        "ppt" { ConvertPptTo-PDF $SourceFile $DestinationFile }
    }   
    Write-Host "1"
}

function ConvertWordTo-PDF {

[CmdletBinding()]

param
(
  
[Parameter (Mandatory=$true,Position=0)]
[String]
$SourceFile,

[Parameter (Mandatory=$true,Position=1)]
[String]
$DestinationFile = "C:/temp/temp.pdf"

)
    $word = New-Object -ComObject word.application 
    $FormatPDF = 17
    $word.visible = $false
    
    If ((Test-Path $SourceFile) -eq $false) {
        throw "Error. Source File $SourceFile not found."
    }

    $doc = $word.documents.open($SourceFile)
    $doc.saveas($DestinationFile,$FormatPDF) 
    $doc.close()
    Write-Output "$SourceFile converted."
    $word.Quit()
}

function ConvertExcelTo-PDF {

[CmdletBinding()]

param
(
  
[Parameter (Mandatory=$true,Position=0)]
[String]
$SourceFile,

[Parameter (Mandatory=$true,Position=1)]
[String]
$DestinationFile = "C:/temp/temp.pdf"

)

    $excel = New-Object -ComObject excel.application 
    $xlFixedFormat = “Microsoft.Office.Interop.Excel.xlFixedFormatType” -as [type] 
    $excel.visible = $false
    
    If ((Test-Path $SourceFile) -eq $false) {
        throw "Error. Source Folder $SourceFile not found."
    }

    $doc = $excel.Workbooks.open($SourceFile) 
    $doc.ExportAsFixedFormat($xlFixedFormat::xlTypePDF, $DestinationFile)
    $doc.Saved = $true 
    $doc.close()

    $excel.Quit()
    
}

function ConvertPptTo-PDF {

    [CmdletBinding()]
    
    param
    (
      
    [Parameter (Mandatory=$true,Position=0)]
    [String]
    $SourceFile,
    
    [Parameter (Mandatory=$true,Position=1)]
    [String]
    $DestinationFile = "C:/temp/temp.pdf"
    
    )
        $ppt_app = New-Object -com powerpoint.application
        # Open it in PowerPoint
        $document = $ppt_app.Presentations.Open($SourceFile)
    
        ##
        ## .net - powershell - extract file name and extension - Stack Overflow
        ## https://stackoverflow.com/questions/9788492/powershell-extract-file-name-and-extension
        ##
        ## If the file is coming off the disk and as others have stated, use the BaseName and Extension properties
        ##
        # Create a name for the PDF document; they are stored in the invocation folder!
        # If you want them to be created locally in the folders containing the source PowerPoint file, replace $curr_path with $_.DirectoryName
        $pdf_filename = $DestinationFile
    
        ##
        ## Presentation.SaveAs Method (PowerPoint)
        ## https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/presentation-saveas-method-powerpoint
        ##
        # Save as PDF -- 17 is the literal value of `wdFormatPDF`
        #$opt= [Microsoft.Office.Interop.PowerPoint.PpSaveAsFileType]::ppSaveAsPDF
        #$document.SaveAs($pdf_filename, $opt)
    
    
        ##
        ## Export PDF with pen markups
        ## Based on this:
        ## https://github.com/netoffice/NetOffice_Import_from_SVN/blob/d44862542576ad1f2b0c7e42421aa42c8a3053c7/Source/PowerPoint/DispatchInterfaces/_Presentation.cs#L2943-L2969
        ## this:
        ## command line - How can I automatically convert PowerPoint to PDF? - Super User
        ## https://superuser.com/questions/641471/how-can-i-automatically-convert-powerpoint-to-pdf
        ## And this:
        ## Powershell script to export Powerpoint Presentations to pdf using the Powerpoint COM API
        ## https://gist.github.com/ap0llo/05cef76e3c4040ee924c4cfeef3f0b40
        ##
    
        # String	The path for the export.
        $exportPath = $pdf_filename
    
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/ppfixedformattype-enumeration-powerpoint
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.interop.powerpoint.ppfixedformattype(v=office.14).aspx
        # ppFixedFormatTypeXPS	XPS format
        # ppFixedFormatTypePDF	PDF format
        $fixedFormatType = [Microsoft.Office.Interop.PowerPoint.PpFixedFormatType]::ppFixedFormatTypePDF
    
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/ppfixedformatintent-enumeration-powerpoint
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.interop.powerpoint.ppfixedformatintent(v=office.14).aspx
        # ppFixedFormatIntentScreen	Intent is to view exported file on screen.
        # ppFixedFormatIntentPrint	Intent is to print exported file.
        $intent = [Microsoft.Office.Interop.PowerPoint.PpFixedFormatIntent]::ppFixedFormatIntentScreen
    
        # https://msdn.microsoft.com/en-us/vba/office-shared-vba/articles/msotristate-enumeration-office
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.core.msotristate.aspx
        # msoTrue	True.
        # msoFalse	False.
        # msoCTrue	Not supported.
        # msoTriStateToggle	Not supported.
        # msoTriStateMixed	Not supported.
        $frameSlides = [Microsoft.Office.Core.MsoTriState]::msoFalse
    
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/ppprinthandoutorder-enumeration-powerpoint
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.interop.powerpoint.ppprinthandoutorder(v=office.14).aspx
        # ppPrintHandoutVerticalFirst	Slides are ordered vertically, with the first slide in the upper-left corner and the second slide below it.
        #								If your language setting specifies a right-to-left language, the first slide is in the upper-right corner with the second slide to the left of it.
        # ppPrintHandoutHorizontalFirst	Slides are ordered horizontally, with the first slide in the upper-left corner and the second slide to the right of it.
        #								If your language setting specifies a right-to-left language, the first slide is in the upper-right corner with the second slide to the left of it.
        $handoutOrder = [Microsoft.Office.Interop.PowerPoint.PpPrintHandoutOrder]::ppPrintHandoutVerticalFirst
    
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/ppprintoutputtype-enumeration-powerpoint
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.interop.powerpoint.ppprintoutputtype(v=office.14).aspx
        # ppPrintOutputSlides	Slides
        # ppPrintOutputTwoSlideHandouts	Two Slide Handouts
        # ppPrintOutputThreeSlideHandouts	Three Slide Handouts
        # ppPrintOutputSixSlideHandouts	Six Slide Handouts
        # ppPrintOutputNotesPages	Notes Pages
        # ppPrintOutputOutline	Outline
        # ppPrintOutputBuildSlides	Build Slides
        # ppPrintOutputFourSlideHandouts	Four Slide Handouts
        # ppPrintOutputNineSlideHandouts	Nine Slide Handouts
        # ppPrintOutputOneSlideHandouts	Single Slide Handouts
        $outputType = [Microsoft.Office.Interop.PowerPoint.PpPrintOutputType]::ppPrintOutputSlides
    
        # https://msdn.microsoft.com/en-us/vba/office-shared-vba/articles/msotristate-enumeration-office
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.core.msotristate.aspx
        # msoTrue	True.
        # msoFalse	False.
        # msoCTrue	Not supported.
        # msoTriStateToggle	Not supported.
        # msoTriStateMixed	Not supported.
        $printHiddenSlides = [Microsoft.Office.Core.MsoTriState]::msoFalse
    
        # Slides.Count 屬性 (PowerPoint)
        # https://msdn.microsoft.com/zh-tw/library/office/ff745960.aspx
        # Presentation.PrintOptions Property (PowerPoint)
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/presentation-printoptions-property-powerpoint
        # PrintOptions.Ranges Property (PowerPoint)
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/printoptions-ranges-property-powerpoint
        # PrintRanges.Add Method (PowerPoint)
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/printranges-add-method-powerpoint
        $printRange = $document.PrintOptions.Ranges.Add(1, $document.Slides.Count)
    
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/ppprintrangetype-enumeration-powerpoint
        # https://msdn.microsoft.com/en-us/library/office/microsoft.office.interop.powerpoint.ppprintrangetype(v=office.14).aspx
        # ppPrintAll	Print all slides in the presentation.
        # ppPrintSelection	Print a selection of slides.
        # ppPrintCurrent	Print the current slide from the presentation.
        # ppPrintSlideRange	Print a range of slides.
        # ppPrintNamedSlideShow	Print a named slide show.
        # ppPrintSection	Print the slides of a slideshow section.
        $rangeType = [Microsoft.Office.Interop.PowerPoint.PpPrintRangeType]::ppPrintAll
    
        # String	The name of the slide show.
        $slideShowName = "Slideshow Name"
    
        # Boolean	Whether the document properties should also be exported. The default is False.
        $includeDocProperties = $false
    
        # Boolean	Whether the IRM settings should also be exported. The default is True.
        $keepIRMSettings = $true
    
        # Boolean	Whether to include document structure tags to improve document accessibility. The default is True.
        $docStructureTags = $true
    
        # Boolean	Whether to include a bitmap of the text. The default is True.
        $bitmapMissingFonts = $true
    
        # Boolean	Whether the resulting document is compliant with ISO 19005-1 (PDF/A). The default is False.
        $useISO19005_1 = $false
    
        # Boolean	Whether the resulting document should include associated pen marks.
        $includeMarkup = $true
    
        # Variant	A pointer to an Office add-in that implements the IMsoDocExporter COM interface and allows calls to an alternate implementation of code. The default is a null pointer.
        $externalExporter = $null
    
        ##
        ## Publishes as PDF or XPS.
        ##
        ## vba - difference between ExportAsFixedFormat2 and ExportAsFixedFormat? - Stack Overflow
        ## https://stackoverflow.com/questions/37585025/difference-between-exportasfixedformat2-and-exportasfixedformat
        ##
    
        # ExportAsFixedFormat2 can include pen markups
        # Presentation.ExportAsFixedFormat2 Method (PowerPoint)
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/presentation-exportasfixedformat2-method-powerpoint
        $document.ExportAsFixedFormat2($exportPath, $fixedFormatType, $intent, $frameSlides, $handoutOrder, $outputType, $printHiddenSlides, $printRange, $rangeType, $slideShowName, $includeDocProperties, $keepIRMSettings, $docStructureTags, $bitmapMissingFonts, $useISO19005_1, $includeMarkup)
    
        # ExportAsFixedFormat cannot include pen markups
        # Presentation.ExportAsFixedFormat Method (PowerPoint)
        # https://msdn.microsoft.com/en-us/vba/powerpoint-vba/articles/presentation-exportasfixedformat-method-powerpoint
        #$document.ExportAsFixedFormat($exportPath, $fixedFormatType, $intent, $frameSlides, $handoutOrder, $outputType, $printHiddenSlides, $printRange, $rangeType, $slideShowName, $includeDocProperties, $keepIRMSettings, $docStructureTags, $bitmapMissingFonts, $useISO19005_1)
    
        # Close PowerPoint file
        $document.Close()
        
        $ppt_app.Quit();
    }