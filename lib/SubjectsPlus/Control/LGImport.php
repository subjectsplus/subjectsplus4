<?php
/**
 * @file LibGuidesImport.php
 * @brief
 * @author little9 (Jamie Little)
 * @date June 2014
 */
namespace SubjectsPlus\Control;


use RichterLibrary\Helpers\CatalogMigrator;

require_once(__DIR__ . "../../../HTMLPurifier/HTMLPurifier.auto.php");

class LGImport
{
    private $_guide_id;
    private $_guide_owner;
    private $_row = 0;
    private $_column = 0;
    private $_staff_id;
    private $log;
    private $titles = array();
    private $dupes = array();
    private $urls = array();

    public function __construct($lib_guides_xml_path, Logger $log, Querier $db, CatalogMigrator $cm)
    {
        $libguides_xml = new \SimpleXMLElement (file_get_contents($lib_guides_xml_path, 'r'));
        $this->libguidesxml = $libguides_xml;
        $this->log = $log;
        $this->db = $db;
        $this->connection = $db->getConnection();
        $this->cm = $cm;
    }

    public function setGuideOwner($guide_owner)
    {
        $this->_guide_owner = $guide_owner;
    }

    public function getGuideOwner()
    {
        return $this->_guide_owner;
    }

    public function setGuideID($guide_id)
    {
        $this->_guide_id = $guide_id;
    }

    public function getGuideID()
    {
        return $this->_guide_id;
    }

    public function setStaffID($staff_id)
    {
        $this->_staff_id = $staff_id;
    }

    public function getStaffID()
    {
        return $this->_staff_id;
    }

    public function setRow($row)
    {
        $this->_row = $row;
    }

    public function getRow()
    {
        $this->_row++;
        if ($this->_row > 2) {
            $this->_row = 0;
        }
        return $this->_row;
    }

    public function setColumn($column)
    {
        $this->_column = $column;
    }

    public function getColumn()
    {
        $this->_column++;
        if ($this->_column > 2) {
            $this->_column = 0;
        }
        return $this->_column;
    }

    public function parseImagesHtml($html) {
        if ($html != '') {
            // Import images and replace the old urls with new urls
            $doc = new \DOMDocument();

            // Add these options -- otherwise you'll get a full HTML document with
            // a doctype when running saveHTML()
            $doc->loadHTML($html);

            // Download images
            $nodes = $doc->getElementsByTagName("img");

            foreach ($nodes as $node) {

                foreach ($node->attributes as $attr) {
                    $test = strpos($attr->value, "http://");

                    if ($test !== false) {
                        $this->log->importLog($attr->value);
                        $attr->value = $this->downloadImages($attr->value);
                        $this->log->importLog($attr->value);
                    }
                }
            }


            $new_html = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $doc->saveHTML()));
            return $new_html;

        }
        


    }


    public function insertBasicPluslet($box, $section_id, $description)
    {
        $row = $this->getRow();
        $column = $this->getColumn();

        $clean_html = $this->purifyHTML($description);

        $box_name = $this->db->quote($box->NAME);

        if ($this->db->exec("INSERT INTO pluslet (pluslet_id, title, body, type) VALUES ('$box->BOX_ID', $box_name, {$this->db->quote($clean_html)}, 'Basic')")) {

            $this->log->importLog("Inserted pluslet '$box->NAME'");
        } else {

            $this->log->importLog("Error inserting pluslet:");
            ob_start();
            var_dump($this->db->errorInfo(ob_get_clean()));
        }

        if ($this->db->exec("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->BOX_ID', '$section_id', $column, $row)")) {
            $this->log->importLog("Inserted pluslet section relationship");

            // This sticks the newly created pluslet into a section
        } else {

            $this->log->importLog("Error inserting pluslet_section:");
            $this->log->importLog($this->db->errorInfo());
            $this->log->importLog("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->BOX_ID', '$section_id', $column, $row)");
        }
    }

    public function insertPluslet($box, $section_id, $pluslet_type, $pluslet_title)
    {
        $row = $this->getRow();
        $column = $this->getColumn();

        $this->db->exec("INSERT INTO pluslet(title, type,body) VALUES ({$this->db->quote($pluslet_title)}, '$pluslet_type','')");

        $pluslet_id = $this->db->last_id();

        $this->db->exec("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$pluslet_id', $section_id, $column, $row)");
    }

    public function insertRSSPluslet($box, $section_id, $feed_url)
    {

        $row = $this->getRow();
        $column = $this->getColumn();

        if ($this->db->exec("INSERT INTO pluslet (pluslet_id, title, body, type, extra) VALUES ($box->BOX_ID, {$this->db->quote($box->NAME)}, '$feed_url', 'Feed', '{\"num_items\":5,  \"show_desc\":1, \"show_feed\": 1, \"feed_type\": \"RSS\"}' )")) {

            $this->log->importLog("Inserted RSS pluslet '$box->NAME'");

        } else {

            $this->log->importLog("INSERT INTO pluslet (pluslet_id, title, body, type) VALUES ('$box->BOX_ID', {$this->db->quote($box->NAME)}, '$feed_url', 'Feed', '' )");

            $this->log->importLog("RSS RSSS RSS Error inserting pluslet:");
            $this->log->importLog($this->db->errorInfo());

        }


        if ($this->db->exec("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->BOX_ID', '$section_id', $column, $row)")) {
            $this->log->importLog("Inserted pluslet section relationship");


            // This sticks the newly created pluslet into a section
        } else {


            $this->log->importLog("RSS Error inserting pluslet_section:");
            $this->log->importLog($this->db->errorInfo());
            $this->log->importLog("RSS INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->BOX_ID', '$section_id', $column, $row)");
        }


    }

    public function insertLinkListPluslet($box, $section_id)
    {
        $row = $this->getRow();
        $column = $this->getColumn();
        $pluslet_title = $box->NAME;

        if (isset($box->DESCRIPTION)) {
            $linkListText = $this->parseImagesHtml($box->DESCRIPTION);
        } else {
            $linkListText = "";
        }

        $links = "";

        if (!isset($box->LINKS->LINK)) {
            $this->insertBasicPluslet($box,$section_id,$linkListText);
            return;
        }

        foreach ($box->LINKS->LINK as $link) {

            $new_url = $link->URL;

            if ($this->cm->whatType($link->URL) == "default") {
                $new_url = $this->cm->removeLegacyCatlog($link->URL, "'" . $link->NAME . "'");
            }

            $record = $this->db->query("SELECT * FROM location WHERE location LIKE " . $this->db->quote($new_url), NULL, TRUE);
            if (isset($record[0]['location_id'])) {
                $record_title = $this->db->query("SELECT title.title,title.title_id, location.location  FROM 
location_title 
JOIN title ON title.title_id = location_title.title_id
JOIN location on location.location_id = location_title.location_id
WHERE location.location_id = " . $record[0]['location_id']);
                $links .= "<li data-location='{$link->URL}' data-record-id='{$record_title[0]['title_id']}' data-title='{$record_title[0]['title']}' data-show-icons='0' data-show-note='0' data-show-description='0'>{{dab},{{$record_title[0]['title_id']}},{{$record_title[0]['title']}},{000}}</li>";

                $this->log->importLog("Insert record:");
                $this->log->importLog($record_title);
                $this->log->importLog("SELECT * FROM location WHERE location LIKE " . $this->db->quote($new_url));


            }

        }
        $linkListBody = "<div class='link-list-text-top'>$linkListText</div><ul class='link-list-display'>$links</ul>";

        $connection = $this->db->getConnection();
        $statement = $connection->prepare("INSERT INTO pluslet(title,type,body) VALUES (:title, :type,:body)");
        $title = $pluslet_title;
        $type = "LinkList";
        $statement->bindParam(':title', $title);
        $statement->bindParam(':body', $linkListBody);
        $statement->bindParam(':type', $type);
        $statement->execute();


        $pluslet_id = $this->db->last_id();

        $this->db->exec("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$pluslet_id', $section_id, $column, $row)");

    }

    public function insertLinkedBox($box, $section_id)
    {


        $row = $this->getRow();
        $column = $this->getColumn();

        if ($this->db->exec("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->LINKED_BOX_ID', '$section_id', $column, $row)")) {
            $this->log->importLog("Inserted linked box");


            // This sticks the newly created pluslet into a section
        } else {


            $this->log->importLog("Error inserting pluslet_section:");
            $this->log->importLog($this->db->errorInfo());
            $this->log->importLog("INSERT INTO pluslet_section (pluslet_id, section_id, pcolumn, prow) VALUES ('$box->LINKED_BOX_ID', '$section_id', $column, $row)");
        }

    }


    public function importBox($box, $section_id)
    {

        // $this->db->exec("SET NAMES utf-8" );
        $description = null;

        // Import images and replace the old urls with new urls
        $pure_html = $this->purifyHTML($box->DESCRIPTION);

        if ($pure_html) {
            $clean_description = $this->parseImagesHtml($pure_html);
            $description .= "<div class=\"description\">" . $clean_description . "</div>";

        }

        // Create html for the description


        switch ($box->BOX_TYPE) {

            case "Text Box":

                $this->insertBasicPluslet($box, $section_id, $description);
                break;
            case "Basic Links":


                $this->insertLinkListPluslet($box, $section_id);
                break;

            case "Complex Links":

                $this->insertLinkListPluslet($box, $section_id);

                break;

            case "Embedded Media & Widgets":

                foreach ($box->EMBEDDED_MEDIA_AND_WIDGETS as $media) {

                    $description .=
                        "<div class=\"embedded-media-widgets\">" .
                        "<div class=\"embedded-media-description\">$media->URL</div>" .
                        "</div>";
                }

                $this->insertBasicPluslet($box, $section_id, $description);
                break;

            case "Linked Box":

                $this->insertLinkedBox($box, $section_id);

                break;

            case "RSS Feeds & Podcasts":
                $feed_url = $box->RSS_FEED->URL;
                $this->insertRSSPluslet($box, $section_id, $feed_url);

                break;

            case "Books":
                // Box type: Books

                foreach ($box->BOOKS->BOOK as $book) {

                    $this->parseLink($book->URL, $book->TITLE, $book->DESCRIPTION);

                    $description .=
                        "<div class=\"book\">" .
                        "<div class=\"book-cover-art\"><img class=\"cover-image\" src=\"$book->COVER_ART\"></div>" .
                        "<a class=\"book-title\" href=\"$book->URL\">$book->TITLE</a>" .
                        "<div class=\"book-author\">$book->AUTHOR</div>" .
                        "<div class=\"book-call-number\">$book->CALL_NUMBER</div>" .
                        "<div class=\"book-description\">$book->DESCRIPTION</div>" .
                        "</div>";
                }

                $this->insertBasicPluslet($box, $section_id, $description);

                break;

            case "User Submitted Links":

            case "Files":

                foreach ($box->FILES as $files) {

                    foreach ($files->FILE as $file) {

                        $description .= "<div class=\"file\">" .
                            "<div class=\"file-title\"><i class=\"fa fa-file\" aria-hidden=\"true\"></i>
<a href=\"http://libguides.miami.edu/loader.php?type=d&id=$file->FILE_ID\">$file->NAME</a></div>"
                            . "<div class=\"file-description\">" . $file->DESCRIPTION . "</div>"

                            . "</div>";
                    }
                }

                $this->insertBasicPluslet($box, $section_id, $description);
                break;
            case "User Feedback":
                break;
            case "Google Search":

                $this->insertPluslet($box, $section_id, "GoogleSearch", "Google Search");
                break;
            case "Poll":
                break;
            case "Google Books":
                $this->insertPluslet($box, $section_id, "GoogleBooks", "Google Books");
                break;
            case "Events":
                break;
            case "Guide Links":
                break;
            case "User Profile":
                $this->insertPluslet($box, $section_id, "SubjectSpecialist", "Subject Specialist");
                break;
            case "Google Scholar":

                $this->insertPluslet($box, $section_id, "GoogleScholar", "Google Scholar");
                break;
            default:
                break;
        }


    }

    public function downloadImages($url)
    {
        // This method creates a folder for a guide image in assets, downloads the image , and then returns the new URL for that image

        global $AssetPath;

        // Download the image with CURL

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);

        // Create a path for the iamge
        $dir_name = dirname(dirname(dirname(dirname(__FILE__)))) . "/assets/images/" . $this->_guide_id . "/";

        // Make the guide's asset directory if needed
        if (!is_dir($dir_name)) {
            mkdir($dir_name, 0777, true);
        }


        // Write the file
        $file_name = substr($url, strrpos($url, '/') + 1);
        $file = fopen($dir_name . $file_name, 'w+');
        fwrite($file, $data);
        fclose($file);


        // Return the new URL
        $img_path = $AssetPath . "/images/" . $this->_guide_id . "/" . $file_name;

        $this->log->importLog($img_path);

        return $img_path;

    }

    public function outputOwners()
    {

        $libguides_xml = $this->libguidesxml;

        $owners = $libguides_xml->xpath("//OWNER");
        $owner_names = array();
        $owner_email = array();


        foreach ($owners as $owner) {
            if (!in_array((string)$owner->NAME, $owner_names)) {

                array_push($owner_names, (string)$owner->NAME);
            }
        }


        foreach ($owners as $owner) {
            if (!in_array((string)$owner->EMAIL_ADDRESS, $owner_email)) {

                array_push($owner_email, (string)$owner->EMAIL_ADDRESS);
            }
        }


        $owners_combined = zip($owner_names, $owner_email);

        echo "<select name=\"email\" class=\"owners\" >";

        foreach ($owners_combined as $owner) {


            echo "<option value=\"$owner[1]\">$owner[0]</option>";


        }
        echo "<select>";


    }

    public function outputGuides($email_address)
    {
        // Outputs a select box for guides

        $libguides_xml = $this->libguidesxml;


        $owners = $libguides_xml->xpath("//OWNER/EMAIL_ADDRESS[.='$email_address']/..");
        $owner_names = array();
        $owner_email = array();

        foreach ($owners as $owner) {
            if (!in_array((string)$owner->NAME, $owner_names)) {

                array_push($owner_names, (string)$owner->NAME);
            }
        }


        foreach ($owners as $owner) {
            if (!in_array((string)$owner->EMAIL_ADDRESS, $owner_email)) {

                array_push($owner_email, (string)$owner->EMAIL_ADDRESS);
            }
        }


        $owners_combined = zip($owner_names, $owner_email);
        $previously_imported = array();


        foreach ($owners_combined as $owner) {

            echo "<h3>" . $owner[0] . "'s Guides</h3>";

            $guide_names = $libguides_xml->xpath("//OWNER/NAME[text() = '$owner[0]']/ancestor::GUIDE");

            echo "<select class=\"guides\" >";

            foreach ($guide_names as $guide) {

                $prexisting_guide = $this->db->query("SELECT * FROM subject WHERE subject_id = $guide->GUIDE_ID");
                var_dump($prexisting_guide);

                if (!$prexisting_guide) {

                    echo "<option value=\"$guide->GUIDE_ID\">$guide->NAME - $guide->STATUS</option>";


                } else {


                }
            }


            echo "</select>";

            foreach ($guide_names as $guide) {

                $guide_imported_count = $this->db->query("SELECT COUNT(*) FROM subject WHERE subject_id = '{$guide->GUIDE_ID}'");


                if ($guide_imported_count[0][0] == "1") {
                    array_push($previously_imported, array("guide_name" => (string)$guide->NAME, "guide_id" => (string)$guide->GUIDE_ID));
                }

            }

            echo "<script>";
            echo "var previously_imported = " . json_encode($previously_imported) . ";";
            echo "</script>";
            echo "</section>";
        }


        return $owners_combined;

    }

    public function guideImported()
    {

        $guide_id = $this->getGuideID();
        $guide = $this->db->query("SELECT COUNT(*) FROM subject WHERE subject_id = '$guide_id'");

        return $guide;

    }

    public function guideDupe($guide_url)
    {

        $guide = $this->db->query("SELECT COUNT(*) FROM location WHERE location = $guide_url");

        return $guide[0][0];

    }

    public function loadLibGuidesXML()
    {

        $libguides_xml = $this->libguidesxml;

        // zip combines arrays in fancy way
        // From the python docs: "This function returns a list of tuples, where the i-th tuple contains the i-th element from each of the argument sequences or iterables. The returned list is truncated in length to the length of the shortest argument sequence."

        $guide_id = $this->getGuideID();

        //Get the guide owner's email address
        $guide_owner_id = $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/following-sibling::OWNER_ACCOUNT_ID");
        $guide_owner_email = $libguides_xml->xpath("//ACCOUNT_ID[.=\"$guide_owner_id[0]\"]/following-sibling::EMAIL");
        $this->setGuideOwner($guide_owner_email);

        $subject_values = zip($libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/following-sibling::NAME"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/GUIDE_ID"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/LAST_UPDATE"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/DESCRIPTION"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/PAGES"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/STATUS"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/PAGES//LINKS"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/OWNER_ACCOUNT_ID"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/OWNER/EMAIL_ADDRESS"),
            $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/parent::GUIDE/TAGS")
        );

        $test = $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/following-sibling::NAME");
        $this->log->importLog($test);


        return $subject_values;

    }

    public function insertChildren()
    {

        $tabs = $this->db->query("SELECT * FROM tab");

        foreach ($tabs as $tab) {
            $tab_id = $tab['tab_id'];

            if ($tab['parent'] == '') {

                $child_ids = array();

                $children = $this->db->query("SELECT * FROM tab WHERE parent = $tab_id");

                foreach ($children as $child) {

                    array_push($child_ids, array("child" => $child['tab_id']));

                }

                $children_json = json_encode($child_ids);
                $this->db->exec("UPDATE tab SET children='$children_json' WHERE tab_id='$tab_id'");
            }
        }


    }

    public function articlesToArray($string)
    {
        $matches = array();
        preg_match("/^\b(the |a |an |la |les |el |las |los |le )\b/i", strip_tags($string), $matches);
        return $matches;
    }

    public function removeArticlesFromString($string)
    {
        $article_free_string = trim(strip_tags(preg_replace("/^\b(the|a|an|la|les|el|las|los)/i", " ", $string)));
        return $article_free_string;
    }

    public function purifyHTML($html)
    {

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.TidyLevel', 'heavy');

        $config->set('HTML.AllowedElements', array('a', 'b', 'p', 'i', 'em', 'u', 'br', 'div', 'img', 'strong', 'iframe', 'ul', 'li', 'ol', 'font', 'table', 'tr', 'td', 'th'));
        $config->set('HTML.AllowedAttributes', array('a.href', 'class', 'img.src', '*.alt', '*.title', '*.border', 'a.target', 'a.rel', 'iframe.src'));
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^http://(www.youtube.com/embed/|player.vimeo.com/video/)%');

        $purifier = new \HTMLPurifier($config);
        $html = str_replace(chr(194), " ", $html);
        return $purifier->purify($html);

    }

    public function parseLink($link_url, $link_name, $link_description)
    {
        

        // Remove the proxy url from the link URL
        $noproxy_url = $this->cm->removeProxy($link_url);
        $noproxy_url = $this->db->quote($noproxy_url);

        $clean_url = str_replace("'", "", $noproxy_url);
        $title = $this->db->quote(strip_tags($link_name));

        $type = $this->cm->whatType($clean_url);



        if ($type == "record") {
            return 0;
        }

        if ($type == "default") {
            $clean_url = $this->cm->removeLegacyCatalog($clean_url);
        }
        if ($type == "mail") {
        return 0;
        }
        if ($type == "libguides") {
            return 0;
        }
        if ($type == "search") {
            return 0;
        }
        if ($type == "image") {
            return 0;
        }

        array_push($this->urls, array("url" => $clean_url));
        array_push($this->titles, array("title" => $title));

        $record_check = $this->db->query("SELECT COUNT(*) FROM location WHERE location = $noproxy_url ");
        $title_check = $this->db->query("SELECT COUNT(*) FROM title WHERE title = $title");

        if ($record_check[0][0] == 0 && $title_check[0][0] == 0) {
            if ($this->db->exec("INSERT INTO location (location, format, access_restrictions, eres_display, ctags) VALUES ({$this->db->quote($clean_url)},1,1,'N', 'LG')")) {

                array_push($this->dupes, array("status" => "New Record Created"));


                $this->log->importLog("Inserted location");
                $location_id = $this->db->last_id();

            } else {

                $this->log->importLog("Error inserting location:");
                $this->log->importLog("\"INSERT INTO location (location, format, access_restrictions, eres_display) VALUES ($new_catalog_url , 1, 1, 'N' )\"");
            }

            // When inserting the titles into the databases, articles (a, an, the) should be removed and then stored in the prefix field
            $matches = $this->articlesToArray(strip_tags($link_name));


            // If there isn't an article in the title
            if (empty($matches[0])) {

                if ($this->db->exec("INSERT INTO title (title, description) VALUES (" . $this->db->quote(strip_tags($link_name)) . "," . $this->db->quote($link_description) . ")")) {
                    $this->log->importLog("Inserted title");
                    $title_id = $this->db->last_id();

                } else {
                    $this->log->importLog("Error inserting title:");
                    $this->log->importLog(serialize($this->db->errorInfo()));
                }

            }

            // If there is an article in the title
            if (isset($matches[0])) {

                $clean_link_name = $this->removeArticlesFromString($link_name);

                if ($this->db->exec("INSERT INTO title (title, description, pre) VALUES (" . $this->db->quote($clean_link_name) . "," . $this->db->quote($link_description) . "," . $this->db->quote($matches[0]) . ")")) {
                    $this->log->importLog("Inserted title");
                    $title_id = $this->db->last_id();

                } else {
                    $this->log->importLog("Error inserting title:");
                    $this->log->importLog(serialize($this->db->errorInfo()));
                }

            }


            if ($this->db->exec("INSERT INTO location_title (title_id, location_id) VALUES ($title_id, $location_id )")) {
                $this->log->importLog("Inserted location_title");


            } else {
                $this->log->importLog("Error inserting location_title:");
                $this->log->importLog(serialize($this->db->errorInfo()));

                $this->log->importLog("INSERT INTO location_title (title_id, location_id) VALUES ($title_id, $location_id)");
            }


        } else {
            array_push($this->dupes, array("status" => "Link Already Imported Into Records"));
        }


    }

    public function parseLinks($link_values)
    {

        $all_titles = array();

        foreach ($link_values as $link) {
            if (isset($link->NAME)) {
                $this->parseLink($link->URL, $link->NAME, $link->DESCRIPTION_SHORT);
            }
            if ($link->TITLE) {
                $this->parseLink($link->URL, $link->TITLE, $link->DESCRIPTION);
            }

        }

        $all_titles['titles'] = zip($this->titles, $this->dupes, $this->urls);

        $return_titles = json_encode($all_titles);

        return $return_titles;
    }

    public function loadLibGuidesLinksXML()
    {
        $guide_id = $this->getGuideID();
        $libguides_xml = $this->libguidesxml;
        $link_values = $libguides_xml->xpath("//GUIDE/GUIDE_ID[.=$guide_id]/..//URL/..");

        $descriptions = $libguides_xml->xpath("//GUIDE/GUIDE_ID[.='$guide_id']/..//DESCRIPTION");

        foreach ($descriptions as $description) {
            if ($description != "") {
                $this->parseLinksFromDescription($description);
            }
        }

        return $this->parseLinks($link_values);

    }

    public function importLibGuides()
    {
        $subject_values = $this->loadLibGuidesXML();
        $response = array();

        foreach ($subject_values as $subject) {
            // Remove the apostrophes and spaces from the shortform
            $shortform = preg_replace("/[^[:alnum:]]/", '', $subject[0]);

            // Escape the apostrophes in the guide name
            $guide_name = str_replace("'", "''", $subject[0]);

            if ($subject[0] != null) {
                if ($this->db->exec("INSERT INTO subject (subject, subject_id, shortform, description, keywords, header, extra) VALUES ({$this->db->quote($guide_name)}, '$subject[1]', {$this->db->quote($shortform)} , {$this->db->quote($subject[3])}, {$this->db->quote($subject[9])},'um','{\"maincol:\"\"}')")) {
                    $response = array("imported_guide" => $subject[1]);
                } else {
                    $response = array("imported_guide" => $subject[1][0]);
                    $query = "INSERT INTO subject (subject, subject_id, shortform, description, keywords, header, extra) VALUES ('$guide_name', '$subject[1]', '$shortform' ,  '$subject[3]', '$subject[9]','um','{\"maincol:\"\"}')";
                    $this->log->importLog("Error inserting subject:");
                    $this->log->importLog($query);
                    $this->log->importLog(serialize($this->db->errorInfo()));
                }

                if ($this->getGuideOwner() != null) {
                    $staff_id = $this->getStaffID();
                    $this->log->importLog("Staff ID: " . $staff_id);

                    if ($this->db->exec("INSERT INTO staff_subject (subject_id, staff_id) VALUES ($subject[1], $staff_id)")) {
                        $this->log->importLog("Inserted staff: '$staff_id'");
                    } else {
                        $this->log->importLog("Error inserting staff. ");
                    }
                }
            }

            $subject_page = $subject[4];
            $tab_index = 0;

            foreach ($subject_page->PAGE as $tab) {
                // LibGuide's pages are tabs so make a new tab
                $tab_index++;
                $visibility = 1;
                $clean_tab_name = $this->db->quote($tab->NAME);
                $parent_id = $tab->PARENT_PAGE_ID;
                $tab_id = $tab->PAGE_ID;
                $external_url = $tab->EXTERNAL_LINK;

                if ($this->db->exec("INSERT INTO tab (tab_id, subject_id, label, tab_index,visibility, parent,children,extra,external_url) VALUES ('$tab->PAGE_ID', '$subject[1]', $clean_tab_name, $tab_index - 1, $visibility, '','','',{$this->db->quote($external_url)})")) {
                    if ($parent_id != '') {
                        $this->db->exec("UPDATE tab SET parent='$parent_id' WHERE tab_id='$tab_id'");
                    }
                    $this->log->importLog("Inserted tab '$tab->NAME'");
                } else {
                    $this->log->importLog("Problem inserting the tab, '$tab->NAME'. This tab may already exist in the database.");
                    $this->log->importLog("Error inserting tab:");
                    $this->log->importLog(serialize($this->db->errorInfo()));
                }

                $section_index = null;

                foreach ($tab->BOXES as $section) {
                    // LibGuide's box parents into sections
                    $section_uniqid = $section_index . rand();
                    $section_index++;

                    if ($this->db->exec("INSERT INTO section (tab_id, section_id, section_index, layout) VALUES ('$tab->PAGE_ID', $section_uniqid ,   $section_index, '3-6-3')")) {
                        $this->log->importLog("Inserted section");
                    } else {
                        $this->log->importLog("Problem inserting this section. This section  may already exist in the database.");
                        $this->log->importLog("Error inserting section:");
                        $this->log->importLog($this->db->errorInfo());
                    }
                }
                foreach ($tab->BOXES->BOX as $pluslet) {
                    // This imports each LibGuide's boxes as pluslets
                    $this->log->importLog("\n");
                    $this->log->importLog((string)$pluslet);
                    $this->log->importLog("\n");

                    $this->importBox($pluslet, $section_uniqid);

                    $box_names = array();
                    $box_types = array();

                    $box_names['box_name'] = $pluslet->NAME;
                    $box_types['box_type'] = $pluslet->BOX_TYPE;
                    $boxes = array($box_names, $box_types);
                    array_push($response, array("box" => $boxes));
                }
            }
        }
        $this->insertChildren();
        return json_encode($response);
    }

    public function parseLinksFromDescription($description)
    {
        $html = new \DOMDocument();
        $html->loadHTML($this->purifyHTML($description));
        foreach ($html->getElementsByTagName("a") as $link) {
            $href = $link->getAttribute("href");

            $this->parseLink($this->cm->removeProxy($href), $link->nodeValue, "");
        }
        return $description;
    }


}