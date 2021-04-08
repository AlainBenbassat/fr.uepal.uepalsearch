<?php
use CRM_Uepalsearch_ExtensionUtil as E;

class CRM_Uepalsearch_Form_Search_Personnes extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  public function buildForm(&$form) {
    CRM_Utils_System::setTitle('Rechercher les personnnes reliés à une inspection et/ou un consistoire et/ou une paroisse');

    $fields = [];
    $fields[] = $this->addFieldRelations($form);
    $fields[] = $this->addFieldInspections($form);
    $fields[] = $this->addFieldConsistoires($form);
    $fields[] = $this->addFieldParoisses($form);


    $form->assign('elements', $fields);
  }

  public function &columns() {
    $columns = [
      'Identifiant' => 'contact_id',
      'Prénom' => 'first_name',
      'Nom' => 'last_name',
      'Adresse e-mail' => 'email',
    ];
    return $columns;
  }

  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //var_dump($sql);exit;
    return $sql;
  }

  public function select() {
    $selectColumns = "
      contact_a.id as contact_id,
      contact_a.first_name,
      contact_a.last_name,
      e.email
    ";

    return $selectColumns;
  }

  public function from() {
    $fromClause = "
      FROM
        civicrm_contact contact_a
      LEFT OUTER JOIN
        civicrm_email e ON e.contact_id = contact_a.id AND e.is_primary = 1
    ";

    return $fromClause;
  }

  public function where($includeContactIDs = FALSE) {
    $params = [];

    $orgContactsFilter = $this->getInspectionConsistoireParoisseFilter();
    $relationshipFilter = $this->getRelationshipFilter();

    $where = "
        contact_a.is_deleted = 0
      and
        contact_a.contact_type = 'Individual'
      and
        exists (
          select
            r.id
          from
            civicrm_relationship r
          left outer join
            civicrm_contact p on r.contact_id_b = p.id
          left outer join
            civicrm_value_paroisse_detail pd on pd.entity_id = p.id
          where
            r.contact_id_a = contact_a.id
          and
            r.is_active = 1
           $orgContactsFilter
           $relationshipFilter
        )
    ";

    return $this->whereClause($where, $params);
  }

  private function getInspectionConsistoireParoisseFilter() {
    $where = '';

    $filter = CRM_Utils_Array::value('filter_inspection_consistoire_reforme', $this->_formValues);
    if ($filter) {
      $where = " and pd.inspection_consistoire_reforme = $filter ";
    }

    $filter = CRM_Utils_Array::value('filter_consistoire_lutherien', $this->_formValues);
    if ($filter) {
      $where = " and pd.consistoire_lutherien = $filter ";
    }

    $filter = CRM_Utils_Array::value('filter_paroisse', $this->_formValues);
    if ($filter) {
      $where = " and p.id = $filter ";
    }

    return $where;
  }

  private function getRelationshipFilter() {
    $where = '';

    $filter = CRM_Utils_Array::value('filter_relations', $this->_formValues);
    if ($filter) {
      $where = " and r.relationship_type_id in(" . implode(',', $filter) . ')';
    }

    return $where;
  }

  private function addFieldInspections(&$form) {
    $fieldName = 'filter_inspection_consistoire_reforme';
    $fieldLabel = 'Inspection';

    $contactList = $this->getContactsOfType('inspection_consistoire_reforme');
    $form->addElement('select', $fieldName, $fieldLabel, $contactList);

    return $fieldName;
  }

  private function addFieldConsistoires(&$form) {
    $fieldName = 'filter_consistoire_lutherien';
    $fieldLabel = 'Consistoire luthérien';

    $contactList = $this->getContactsOfType('consistoire_lutherien');
    $form->addElement('select', $fieldName, $fieldLabel, $contactList);

    return $fieldName;
  }

  private function addFieldParoisses(&$form) {
    $fieldName = 'filter_paroisse';
    $fieldLabel = 'Paroisse';

    $contactList = $this->getContactsOfType('paroisse');
    $form->addElement('select', $fieldName, $fieldLabel, $contactList);

    return $fieldName;
  }

  private function addFieldRelations(&$form) {
    $fieldName = 'filter_relations';
    $fieldLabel = 'Relations';

    $contactList = $this->getRelationships();
    $form->add('select', $fieldName, $fieldLabel, $contactList, TRUE, [
      'class' => 'crm-select2 huge',
      'multiple' => 'multiple',
    ]);

    return $fieldName;
  }

  private function getContactsOfType($subType) {
    $orgs = [];

    $sql = "
      select
        id,
        organization_name
      from
        civicrm_contact
      where
        is_deleted = 0
      and
        contact_type = 'Organization'
      and
        contact_sub_type like '%$subType%'
      order by
        organization_name
	  ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    // add an empty item at the beginning
    $orgs[''] = '';

    while ($dao->fetch()) {
      $orgs[$dao->id] = $dao->organization_name;
    }

    return $orgs;
  }

  private function getRelationships() {
    $rels = [];

    $sql = "
      select
        id
        , label_a_b
      from
        civicrm_relationship_type crt
      where
        contact_type_a  = 'Individual'
      and
        contact_type_b = 'Organization'
      and
        is_active = 1
      and
        id >= 11
	  ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $rels[$dao->id] = $dao->label_a_b;
    }

    return $rels;
  }

  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

}
