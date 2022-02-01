<?php
/**
 * Created by PhpStorm.
 * User: cbrownroberts
 * Date: 1/5/16
 * Time: 1:45 PM
 */

namespace SubjectsPlus\Control\Guide;

use SubjectsPlus\Control\Querier;

use SubjectsPlus\Control\Interfaces\OutputInterface;

class PlusletData extends GuideBase implements OutputInterface {

	protected $db;

	public $pluslet_ids;
	public $cloned_pluslets;
	public $clones_by_tab;
	public $clones_by_subject;
	public $clone_parents_by_section;

	public function __construct( Querier $db ) {
		$this->db = $db;
	}


	public function dropSavePluslet() {
		$connection = $this->db->getConnection();

	}

	public function fetchPlusletById( $pluslet_id ) {
		$connection = $this->db->getConnection();
		$statement  = $connection->prepare( "SELECT * FROM pluslet WHERE pluslet_id = :pluslet_id" );

		$statement->bindParam( ":pluslet_id", $pluslet_id );
		$statement->execute();
		$pluslet = $statement->fetch();

		return $pluslet;
	}

	public function fetchAllPluslets() {
		// Find ALL our existing pluslets from all guides
		$statement = $this->connection->prepare( "SELECT DISTINCT pluslet_id, title, body, clone, type, extra, hide_titlebar,
                                                           titlebar_styling, favorite_box, target_blank_links
                                          FROM pluslet" );

		$statement->execute();
		$pluslets = $statement->fetchAll();

		$this->pluslets = $pluslets;
	}

	public function fetchAllPlusletIds() {
		// Find ALL our existing pluslet ids from all guides
		$connection = $this->db->getConnection();
		$statement  = $connection->prepare( "SELECT pluslet_id FROM pluslet" );

		$statement->execute();
		$pluslet_ids = $statement->fetchAll();

		$this->pluslet_ids = $pluslet_ids;
	}

	public function fetchPlusletsBySubjectId( $subject_id = null ) {
		$connection = $this->db->getConnection();

		$pluslets_statement = $connection->prepare( "SELECT * FROM subject
                                INNER JOIN tab on tab.subject_id = subject.subject_id
                                INNER JOIN section on tab.tab_id = section.tab_id
                                INNER JOIN pluslet_section on section.section_id = pluslet_section.section_id
                                INNER JOIN pluslet on pluslet_section.pluslet_id = pluslet.pluslet_id
                            WHERE subject.subject_id = :subject_id" );
		$pluslets_statement->bindParam( ":subject_id", $subject_id );
		$pluslets_statement->execute();
		$pluslets = $pluslets_statement->fetchAll();

		return $pluslets;
	}

	public function fetchPlusletsBySubjectIdTabId( $subject_id = null, $tab_id = null ) {
		$connection = $this->db->getConnection();

		$pluslets_statement = $connection->prepare( "SELECT * FROM subject
                                INNER JOIN tab on tab.subject_id = subject.subject_id
                                INNER JOIN section on tab.tab_id = section.tab_id
                                INNER JOIN pluslet_section on section.section_id = pluslet_section.section_id
                                INNER JOIN pluslet on pluslet_section.pluslet_id = pluslet.pluslet_id
                            WHERE subject.subject_id = :subject_id
                            AND tab.tab_id = :tab_id" );
		$pluslets_statement->bindParam( ":subject_id", $subject_id );
		$pluslets_statement->bindParam( ":tab_id", $tab_id );
		$pluslets_statement->execute();
		$pluslets = $pluslets_statement->fetchAll();

		return $pluslets;
	}

	public function fetchPlusletsBySectionId( $section_id = null ) {
		$connection = $this->db->getConnection();
		$statement  = $connection->prepare( "SELECT * FROM pluslet_section WHERE section_id = :section_id " );
		$statement->bindParam( ":section_id", $section_id );
		$statement->execute();
		$pluslets = $statement->fetchAll();

		$this->pluslets = $pluslets;
		return $pluslets;
	}

	public function fetchPlusletsByTabId( $tab_id = null ) {
		$connection = $this->db->getConnection();

		$pluslets_statement = $connection->prepare( "SELECT * FROM subject
                                INNER JOIN tab on tab.subject_id = subject.subject_id
                                INNER JOIN section on tab.tab_id = section.tab_id
                                INNER JOIN pluslet_section on section.section_id = pluslet_section.section_id
                                INNER JOIN pluslet on pluslet_section.pluslet_id = pluslet.pluslet_id
                            WHERE tab.tab_id = :tab_id" );
		$pluslets_statement->bindParam( ":tab_id", $tab_id );
		$pluslets_statement->execute();
		$pluslets = $pluslets_statement->fetchAll();

		return $pluslets;
	}

	public function fetchClonedPlusletsById( $master_id = null ) {
		//pluslets by number type mess up the LIKE query
		if ( strlen( $master_id ) == 1 ) {
			$cloned_pluslets       = array();
			$this->cloned_pluslets = $cloned_pluslets;
		} else {
			// Find ALL our existing pluslet ids from all guides
			$connection = $this->db->getConnection();
			$statement  = $connection->prepare( "SELECT * FROM pluslet WHERE type like 'Clone' AND extra LIKE '%master%' AND extra LIKE '%{$master_id}%' " );
			$statement->execute();
			$cloned_pluslets = $statement->fetchAll();

			$this->cloned_pluslets = $cloned_pluslets;
		}

		return $cloned_pluslets;
	}

	public function fetchClonedParentPlusletsBySectionId( $section_id ) {
		$pluslets   = $this->fetchPlusletsBySectionId( $section_id );

		$master_ids = array();
		foreach ( $pluslets as $pluslet ):
			$master_ids[] = $pluslet["pluslet_id"];
		endforeach;

		$clone_parents_by_section = array();
		foreach ( $master_ids as $master_id ):
			$cloned_pluslets_by_id = $this->fetchClonedPlusletsById( $master_id );

			if ( ! empty( $cloned_pluslets_by_id ) ) {

				$parent_pluslet = $this->fetchPlusletById($master_id);


				$clone_parents_by_section[] = $parent_pluslet;
			}

		endforeach;

		$this->clone_parents_by_section = $clone_parents_by_section;
	}

	public function getClonedPlusletsBySubjectIdTabId( $tab_id ) {
		$pluslets   = $this->fetchPlusletsByTabId( $tab_id );
		$master_ids = array();
		foreach ( $pluslets as $pluslet ):
			$master_ids[] = $pluslet["pluslet_id"];
		endforeach;

		$clones_by_tab = array();
		foreach ( $master_ids as $master_id ):
			$cloned_pluslets_by_id = $this->fetchClonedPlusletsById( $master_id );

			if ( ! empty( $cloned_pluslets_by_id ) ) {
				$clones_by_tab[] = $cloned_pluslets_by_id;
			}

		endforeach;

		$this->clones_by_tab = $clones_by_tab;
	}

	public function getClonedPlusletsBySubjectId( $subject_id ) {
		$pluslets   = $this->fetchPlusletsBySubjectId( $subject_id );
		$master_ids = array();
		foreach ( $pluslets as $pluslet ):
			$master_ids[] = $pluslet["pluslet_id"];
		endforeach;

		$clones_by_subject = array();
		foreach ( $master_ids as $master_id ):
			$clones_by_subject[] = $this->fetchClonedPlusletsById( $master_id );
		endforeach;

		$this->clones_by_subject = $clones_by_subject;

		return $clones_by_subject;
	}

	public function toArray() {
		return get_object_vars( $this );
	}

	public function toJSON() {
		return json_encode( get_object_vars( $this ) );
	}
}