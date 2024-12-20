<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.5                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2014
 * $Id$
 *
 */

/**
 * This class is used to retrieve and display a range of
 * contacts that match the given criteria (specifically for
 * results of advanced search options.
 *
 */
class CRM_Mailing_Selector_Search extends CRM_Core_Selector_Base implements CRM_Core_Selector_API {

  /**
   * This defines two actions- View and Edit.
   *
   * @var array
   * @static
   */
  static $_links = NULL;

  /**
   * We use desc to remind us what that column is, name is used in the tpl
   *
   * @var array
   * @static
   */
  static $_columnHeaders;

  /**
   * Properties of contact we're interested in displaying
   * @var array
   * @static
   */
  static $_properties = array(
    'contact_id',
    'mailing_id',
    'mailing_name',
    'sort_name',
    'email',
    'mailing_subject',
    'email_on_hold',
    'contact_opt_out',
    'mailing_job_status',
    'mailing_job_end_date'
  );

  /**
   * Are we restricting ourselves to a single contact
   *
   * @access protected
   * @var boolean
   */
  protected $_single = FALSE;

  /**
   * Are we restricting ourselves to a single contact
   *
   * @access protected
   * @var boolean
   */
  protected $_limit = NULL;

  /**
   * What context are we being invoked from
   *
   * @access protected
   * @var string
   */
  protected $_context = NULL;

  /**
   * What component context are we being invoked from
   *
   * @access protected
   * @var string
   */
  protected $_compContext = NULL;

  /**
   * QueryParams is the array returned by exportValues called on
   * the HTML_QuickForm_Controller for that page.
   *
   * @var array
   * @access protected
   */
  public $_queryParams;

  /**
   * Represent the type of selector
   *
   * @var int
   * @access protected
   */
  protected $_action;

  /**
   * The additional clause that we restrict the search with
   *
   * @var string
   */
  protected $_mailingClause = NULL;

  /**
   * The query object
   *
   * @var string
   */
  protected $_query;

  /**
   * Class constructor
   *
   * @param array $queryParams array of parameters for query
   * @param \const|int $action - action of search basic or advanced.
   * @param string $mailingClause if the caller wants to further restrict the search
   * @param boolean $single are we dealing only with one contact?
   * @param int $limit how many mailing do we want returned
   *
   * @param string $context
   * @param null $compContext
   *
   * @return \CRM_Mailing_Selector_Search
  @access public
   */
  function __construct(&$queryParams,
    $action = CRM_Core_Action::NONE,
    $mailingClause = NULL,
    $single = FALSE,
    $limit = NULL,
    $context = 'search',
    $compContext = NULL
  ) {
    // submitted form values
    $this->_queryParams = &$queryParams;

    $this->_single      = $single;
    $this->_limit       = $limit;
    $this->_context     = $context;
    $this->_compContext = $compContext;

    $this->_mailingClause = $mailingClause;

    // type of selector
    $this->_action = $action;
    $this->_query = new CRM_Contact_BAO_Query($this->_queryParams,
      CRM_Mailing_BAO_Query::defaultReturnProperties(CRM_Contact_BAO_Query::MODE_MAILING,
        FALSE
      ),
      NULL, FALSE, FALSE,
      CRM_Contact_BAO_Query::MODE_MAILING
    );

    $this->_query->_distinctComponentClause = " civicrm_mailing_recipients.id ";
  }

  /**
   * This method returns the links that are given for each search row.
   * currently the links added for each row are
   *
   * - View
   * - Edit
   *
   * @return array
   * @access public
   *
   */
  static function &links() {
    if (!(self::$_links)) {
    list($context, $key) = func_get_args();
    $extraParams = ($key) ? "&key={$key}" : NULL;
    $searchContext = ($context) ? "&context=$context" : NULL;

    self::$_links = array(
      CRM_Core_Action::VIEW => array(
        'name' => ts('View'),
        'url' => 'civicrm/contact/view',
        'qs' => "reset=1&cid=%%cid%%{$searchContext}{$extraParams}",
        'title' => ts('View Contact Details'),
      ),
      CRM_Core_Action::UPDATE => array(
        'name' => ts('Edit'),
        'url' => 'civicrm/contact/add',
        'qs' => "reset=1&action=update&cid=%%cid%%{$searchContext}{$extraParams}",
        'title' => ts('Edit Contact Details'),
      ),
      CRM_Core_Action::DELETE => array(
        'name' => ts('Delete'),
        'url' => 'civicrm/contact/view/delete',
        'qs' => "reset=1&delete=1&cid=%%cid%%{$searchContext}{$extraParams}",
        'title' => ts('Delete Contact'),
      ),
    );
    }
    return self::$_links;
  }

  /**
   * Getter for array of the parameters required for creating pager.
   *
   * @param $action
   * @param array $params
   *
   * @access public
   */
  function getPagerParams($action, &$params) {
    $params['status'] = ts('Mailing Recipient') . ' %%StatusMessage%%';
    $params['csvString'] = NULL;
    if ($this->_limit) {
      $params['rowCount'] = $this->_limit;
    }
    else {
      $params['rowCount'] = CRM_Utils_Pager::ROWCOUNT;
    }

    $params['buttonTop'] = 'PagerTopButton';
    $params['buttonBottom'] = 'PagerBottomButton';
  }

  /**
   * Returns total number of rows for the query.
   *
   * @param
   *
   * @return int Total number of rows
   * @access public
   */
  function getTotalCount($action) {
    return $this->_query->searchQuery(0, 0, NULL,
      TRUE, FALSE,
      FALSE, FALSE,
      FALSE,
      $this->_mailingClause
    );
  }

  /**
   * Returns all the rows in the given offset and rowCount
   *
   * @param enum   $action   the action being performed
   * @param int    $offset   the row number to start from
   * @param int    $rowCount the number of rows to return
   * @param string $sort     the sql string that describes the sort order
   * @param enum   $output   what should the result set include (web/email/csv)
   *
   * @return int   the total number of rows for this action
   */
  function &getRows($action, $offset, $rowCount, $sort, $output = NULL) {
    $result = $this->_query->searchQuery($offset, $rowCount, $sort,
      FALSE, FALSE,
      FALSE, FALSE,
      FALSE,
      $this->_mailingClause
    );

    // process the result of the query
    $rows = array();
    $permissions = array(CRM_Core_Permission::getPermission());
    if (CRM_Core_Permission::check('delete contacts')) {
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);
    $qfKey = $this->_key;

    While ($result->fetch()) {
      $row = array();
      // the columns we are interested in
      foreach (self::$_properties as $property) {
        if (property_exists($result, $property)) {
          $row[$property] = $result->$property;
        }
      }

      $row['checkbox'] = CRM_Core_Form::CB_PREFIX . $result->mailing_recipients_id;

      $actions = array(
        'cid' => $result->contact_id,
        'cxt' => $this->_context,
      );

      $row['action'] = CRM_Core_Action::formLink(
        self::links($qfKey, $this->_context),
        $mask,
        $actions,
        ts('more'),
        FALSE,
        'contact.mailing.row',
        'Contact',
        $result->contact_id
      );
      $row['contact_type'] = CRM_Contact_BAO_Contact_Utils::getImage($result->contact_sub_type ?
        $result->contact_sub_type : $result->contact_type, FALSE, $result->contact_id
      );

      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * @return array   $qill         which contains an array of strings
   * @access public
   */

  // the current internationalisation is bad, but should more or less work
  // for most of "European" languages
  public function getQILL() {
    return $this->_query->qill();
  }

  /**
   * Returns the column headers as an array of tuples:
   * (name, sortName (key to the sort array))
   *
   * @param string $action the action being performed
   * @param enum   $output what should the result set include (web/email/csv)
   *
   * @return array the column headers that need to be displayed
   * @access public
   */
  public function &getColumnHeaders($action = NULL, $output = NULL) {
    if (!isset(self::$_columnHeaders)) {
      self::$_columnHeaders = array(
        array('desc' => ts('Contact Type')),
        array(
          'name' => ts('Name'),
          'sort' => 'sort_name',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array(
          'name' => ts('Email'),
          'sort' => 'email',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array(
          'name' => ts('Mailing Name'),
          'sort' => 'mailing_name',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array(
          'name' => ts('Mailing Subject'),
          'sort' => 'mailing_subject',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array(
          'name' => ts('Mailing Status'),
          'sort' => 'mailing_job_status',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array(
          'name' => ts('Completed Date'),
          'sort' => 'mailing_job_end_date',
          'direction' => CRM_Utils_Sort::DONTCARE,
        ),
        array('desc' => ts('Actions')),
      );
    }
    return self::$_columnHeaders;
  }

  /**
   * @return mixed
   */
  function alphabetQuery() {
    return $this->_query->searchQuery(NULL, NULL, NULL, FALSE, FALSE, TRUE);
  }

  /**
   * @return string
   */
  function &getQuery() {
    return $this->_query;
  }

  /**
   * Name of export file.
   *
   * @param string $output type of output
   *
   * @return string name of the file
   */
  function getExportFileName($output = 'csv') {
    return ts('CiviCRM Mailing Search');
  }
}
