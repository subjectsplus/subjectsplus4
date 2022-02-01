<?php
/**
 * User: cbrownroberts
 * Date: 09/12/19
 * Time: 13:45 AM
 */
namespace SubjectsPlus\Control\Guide;

use PDO;
use SubjectsPlus\Control\Querier;
use SubjectsPlus\Control\Interfaces\OutputInterface;

class GuideData implements OutputInterface {


	private $_db;
	private $_connection;
	public $guide;
	public $tabs = array ();

	public function __construct(Querier $db) {
		$this->_db = $db;
		$this->_connection = $this->_db->getConnection();
		$this->_connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		$this->_connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	public function fetchGuideData($subject_id) {
		$statement  = $this->_connection->prepare( "SELECT * FROM subject WHERE subject_id = :subject_id" );
		$statement->bindParam( ":subject_id", $subject_id );
		$statement->execute();

		$this->guide = $statement->fetch();

		$tabs = $this->fetchTabsBySubjectId($subject_id);
		$tab_array = array();
		foreach($tabs as $tab):
			array_push($tab_array, $tab);

			$sections = $this->fetchSectionDataByTabId($tab['tab_id']);
			$section_array = array();
			foreach($sections as $section):
				array_push($section_array, $section);

				$pluslets = $this->fetchExistingPlusletDataBySubjectIdTabIdSectionId($subject_id, $tab['tab_id'], $section['section_id']);
				$pluslets_array = array();
				foreach($pluslets as $pluslet):
					array_push($pluslets_array, $pluslet);
				endforeach;

				array_push($section_array, $pluslets_array);

			endforeach;
			array_push($tab_array, $section_array);

		endforeach;
		$this->tabs = $tab_array;

		return $this;
	}


	public function fetchTabsBySubjectId($subject_id = null) {
		$tabs_statement = $this->_connection->prepare ( "SELECT tab.tab_id, tab.label, tab.tab_index, tab.external_url, tab.visibility, tab.parent, tab.children, tab.extra FROM subject
                            INNER JOIN tab on tab.subject_id = subject.subject_id
                            WHERE subject.subject_id = :subject_id" );
		$tabs_statement->bindParam ( ":subject_id", $subject_id );
		$tabs_statement->execute ();
		return $tabs_statement->fetchAll ();
	}


	public function fetchSectionDataByTabId($tab_id = null) {
		$sections_statement = $this->_connection->prepare ( "SELECT * FROM section
                                WHERE tab_id = :tab_id" );
		$sections_statement->bindParam ( ":tab_id", $tab_id );
		$sections_statement->execute ();
		return $sections_statement->fetchAll ();
	}

	public function fetchExistingPlusletDataBySubjectIdTabIdSectionId($subject_id = null, $tab_id = null, $section_id = null) {
		$pluslets_statement = $this->_connection->prepare ( "SELECT pluslet.pluslet_id, pluslet.title, pluslet.body, pluslet.local_file, pluslet.clone, pluslet.type, pluslet.extra, pluslet.hide_titlebar, pluslet.collapse_body, pluslet.titlebar_styling, pluslet.favorite_box, pluslet.target_blank_links, pluslet.master,  pluslet_section.prow, pluslet_section.pcolumn FROM subject
                                INNER JOIN tab on tab.subject_id = subject.subject_id
                                INNER JOIN section on tab.tab_id = section.tab_id
                                INNER JOIN pluslet_section on section.section_id = pluslet_section.section_id
                                INNER JOIN pluslet on pluslet_section.pluslet_id = pluslet.pluslet_id
                            WHERE subject.subject_id = :subject_id
                            AND section.section_id = :section_id
                            AND section.tab_id = :tab_id
							AND pluslet.type != 'Special' " );
		$pluslets_statement->bindParam ( ":subject_id", $subject_id );
		$pluslets_statement->bindParam ( ":section_id", $section_id );
		$pluslets_statement->bindParam ( ":tab_id", $tab_id );
		$pluslets_statement->execute ();
		return $pluslets_statement->fetchAll ();
	}


	public function toArray() {
		return get_object_vars ( $this );
	}
	public function toJSON() {
		return json_encode ( get_object_vars ( $this ) );
	}
}