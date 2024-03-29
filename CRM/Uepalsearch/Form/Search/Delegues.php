<?php

use CRM_Uepalsearch_ExtensionUtil as E;

class CRM_Uepalsearch_Form_Search_Delegues extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  private $config;

  public function __construct(&$formValues) {
    $this->config = new CRM_Uepalconfig_Config();

    parent::__construct($formValues);
  }

  public function buildForm(&$form) {
    CRM_Utils_System::setTitle('Rechercher les délégués (suppléant)');

    $fields = [];

    $fields[] = $this->addFieldInspectionOrConsistoire($form);

    $form->assign('elements', $fields);
  }

  public function &columns() {
    $columns = [
      'Organisation' => 'inspec_consist.organization_name',
      'Relation' => 'relationship_name',
      'Paroisse' => 'r.description',
      'Civilité' => 'prefix',
      'Titre officiel' => 'contact_a.formal_title',
      'Prénom' => 'contact_a.first_name',
      'Nom' => 'contact_a.last_name',
      'Courriel' => 'e.email',
      'Téléphone' => 'p.phone',
      'Complément 1' => 'a.supplemental_address_1',
      'Complément 2' => 'a.supplemental_address_2',
      'Rue' => 'a.street_address',
      'CP' => 'a.postal_code',
      'Ville' => 'a.city',
      'Redelegation' => 'redelegation',
    ];

    return $columns;
  }

  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //var_dump($sql);exit;
    return $sql;
  }

  public function select() {
    $relDelegueId = $this->config->getRelationshipType_estDelegueDe()['id'];
    $relDelegueSuppleantId = $this->config->getRelationshipType_estDelegueSuppleantDe()['id'];

    $inspecOrConsist = $this->getInspectionOrConsistoireFilter();

    $redelegation = "
      select
        group_concat(rdc.organization_name)
      from
        civicrm_relationship rdr
      inner join
        civicrm_contact rdc on rdc.id = rdr.contact_id_b
      where
        rdr.is_active = 1
      and
        rdr.contact_id_a = contact_a.id
      and
        rdr.relationship_type_id in ($relDelegueId, $relDelegueSuppleantId)
      and
        ifnull(rdc.contact_sub_type, '') = ''
    ";

    $selectColumns = "
      inspec_consist.organization_name,
      pref.label prefix,
      contact_a.formal_title,
      contact_a.first_name,
      contact_a.last_name,
      rt.label_b_a relationship_name,
      r.description,
      e.email,
      p.phone,
      a.*,
      ($redelegation) redelegation
    ";

    return $selectColumns;
  }

  public function from() {
    $fromClause = "
      FROM
        civicrm_contact contact_a
      INNER JOIN
        civicrm_relationship r on r.contact_id_a = contact_a.id
      INNER JOIN
        civicrm_relationship_type rt on rt.id = r.relationship_type_id
      INNER JOIN
        civicrm_contact inspec_consist on inspec_consist.id = r.contact_id_b
      LEFT OUTER JOIN
        civicrm_option_value pref on pref.value = contact_a.prefix_id and pref.option_group_id = 6
      LEFT OUTER JOIN
        civicrm_email e ON e.contact_id = contact_a.id AND e.is_primary = 1
      LEFT OUTER JOIN
        civicrm_phone p ON p.contact_id = contact_a.id AND p.is_primary = 1
      LEFT OUTER JOIN
        civicrm_address a ON a.contact_id = contact_a.id AND a.is_primary = 1
    ";

    return $fromClause;
  }

  public function where($includeContactIDs = FALSE) {
    $params = [];

    $relDelegueId = $this->config->getRelationshipType_estDelegueDe()['id'];
    $relDelegueSuppleantId = $this->config->getRelationshipType_estDelegueSuppleantDe()['id'];

    $inspecOrConsist = $this->getInspectionOrConsistoireFilter();

    $where = "
        contact_a.is_deleted = 0
      and
        contact_a.contact_type = 'Individual'
      and
        r.relationship_type_id in ($relDelegueId, $relDelegueSuppleantId)
      and
        r.is_active = 1
      and
        inspec_consist.contact_sub_type like '%$inspecOrConsist%'
    ";

    return $this->whereClause($where, $params);
  }



  private function getInspectionOrConsistoireFilter() {
    $filter = CRM_Utils_Array::value('filter_contact_sub_type', $this->_formValues);

    return $filter;
  }

  private function addFieldInspectionOrConsistoire(&$form) {
    $fieldName = 'filter_contact_sub_type';

    $contactSubTypeList = $this->getContactSubTypeList();
    $form->addRadio($fieldName, 'Délégué de', $contactSubTypeList, [], '<br>', TRUE);

    return $fieldName;
  }

  private function getContactSubTypeList() {
    $list = [
      'inspection_consistoire_reforme' => 'Inspection / Consistoire réformé',
      'consistoire_lutherien' => 'Consistoire luthérien',
    ];

    return $list;
  }

  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }
}
