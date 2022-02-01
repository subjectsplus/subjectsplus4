<?php

namespace SubjectsPlus\API;

use SubjectsPlus\Control\Querier;

class StaffWebService extends WebService implements InterfaceWebService {
	/**
	 * StaffWebService::__construct() - pass parameters to parent construct and
	 * set the service and tag properties
	 *
	 * @param array $lobjUrlParams
	 * @param DBConnector $lobjDBConnector
	 */
	function __construct( $lobjUrlParams, $lobjDBConnector ) {
		parent::__construct( $lobjUrlParams, $lobjDBConnector );
		$this->mstrService = 'staff';
		$this->mstrTag     = 'staff-member';
	}

	/**
	 * StaffWebService::setData() - this method overrides the parent method because
	 * the staff webservice requires an append to the tel field
	 *
	 * @return void
	 */
	public function setData() {
		$lobjParams = $this->mobjUrlParams;

		$lobjParams = $this->sanitizeParams( $lobjParams );

		if ( $lobjParams === false ) {
			die;
		}

		$lstrQuery = $this->generateQuery( $lobjParams ) or die;

		$lobjQuerier = new Querier();

		$lobjResults = $lobjQuerier->query( $lstrQuery, \PDO::FETCH_ASSOC );

		if ( ! $lobjResults ) {
			$lobjResults = array();
		}

		global $tel_prefix;

		foreach ( $lobjResults as &$lobjRow ) {
			if ( isset( $tel_prefix ) ) {
				$lobjRow['tel'] = $tel_prefix . $lobjRow['tel'];
			}
		}

		$this->mobjData[ $this->mstrTag ] = $lobjResults;
	}

	function sanitizeParams( Array $lobjParams ) {
		$lobjFinalParams = array();

		foreach ( $lobjParams as $lstrKey => $lstrValue ) {
			switch ( strtolower( $lstrKey ) ) {
				case 'department':
					$lobjSplit = explode( ',', $lstrValue );

					foreach ( $lobjSplit as &$lstrUnScrubbed ) {
						$lstrUnScrubbed = scrubData( $lstrUnScrubbed, 'integer' );
					}

					$lobjFinalParams['department'] = $lobjSplit;
					break;
				case 'email':
					$lobjSplit = explode( ',', $lstrValue );

					foreach ( $lobjSplit as &$lstrUnScrubbed ) {
						$lstrUnScrubbed = scrubData( $lstrUnScrubbed );
					}

					$lobjFinalParams['email'] = $lobjSplit;
					break;
				case 'max':
					$lstrValue = scrubData( $lstrValue, 'integer' );

					$lobjFinalParams['max'] = $lstrValue;
					break;
				case 'personnel':
					$lobjSplit = explode( ',', $lstrValue );

					foreach ( $lobjSplit as &$lstrUnScrubbed ) {
						$lstrUnScrubbed = scrubData( $lstrUnScrubbed );
					}

					$lobjFinalParams['personnel'] = $lobjSplit;
					break;
			}
		}

		return $lobjFinalParams;
	}

	function generateQuery( Array $lobjParams ) {
		$lstrQuery = 'SELECT lname, fname, title, tel, email, bio
					FROM staff';

		$lobjConditions = array();

		foreach ( $lobjParams as $lstrKey => $lobjValues ) {
			switch ( $lstrKey ) {
				case 'department':
					$lobjCondition = array();

					foreach ( $lobjValues as $lintDepartmentID ) {
						array_push( $lobjCondition, "department_id = '$lintDepartmentID'\n" );
					}

					$lstrCombine = implode( ' OR ', $lobjCondition );

					array_push( $lobjConditions, $lstrCombine );
					break;
				case 'email':
					$lobjCondition = array();

					foreach ( $lobjValues as $lstrEmail ) {
						array_push( $lobjCondition, "email = '$lstrEmail'\n" );
					}

					$lstrCombine = implode( ' OR ', $lobjCondition );

					array_push( $lobjConditions, $lstrCombine );
					break;
				case 'personnel':
					$lstrQuery = 'SELECT staff_id                 AS id,
       lname,
       fname,
       title,
       tel,
       a.email                  as email,
       bio,
       department.department_id as department_id,
       department.name          as department_name,
       department.telephone     as department_telephone,
       room_number,
       IF((SELECT staff_id FROM staff as b WHERE b.staff_id = a.supervisor_id AND b.active = 1) IS NOT NULL,
          a.supervisor_id, "")  as supervisor_id,
       IFNULL((
SELECT GROUP_CONCAT(subject SEPARATOR \', \') as subject_areas FROM staff_subject ss, subject s
                                        WHERE ss.subject_id = s.subject_id
	                                    AND ss.staff_id = a.staff_id
	                                    AND active = \'1\'
	                                    AND s.type = \'Subject\'
                                        AND a.ptags LIKE \'%librarian%\'
	                                    ORDER BY subject), "") as librarian_subject_areas,
        LOWER(user_type.user_type) as user_type
FROM staff as a, user_type as user_type,
     department as department
WHERE active = 1
  AND department.department_id = a.department_id
  AND a.user_type_id = user_type.user_type_id
AND a.user_type_id = 1
ORDER BY a.staff_id';

					break;
			}
		}

		if ( count( $lobjConditions ) > 0 ) {
			$lstrQuery .= "\nWHERE (" . implode( ') AND (', $lobjConditions );
			$lstrQuery .= ')';
		}

		if ( isset( $lobjParams['max'] ) ) {
			$lstrQuery .= " LIMIT 0,{$lobjParams['max']}";
		}

		return $lstrQuery;
	}
}


?>