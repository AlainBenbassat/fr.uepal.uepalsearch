<?php
use CRM_Uepalsearch_ExtensionUtil as E;

class CRM_Uepalsearch_Form_Search_Personnes extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  private $relTypesParoisse;

  public function __construct(&$formValues) {
    $this->relTypesParoisse = $this->getNecessaryRelationships();

    parent::__construct($formValues);
  }

  public function buildForm(&$form) {
    CRM_Utils_System::setTitle('Rechercher les personnnes reliés à une inspection et/ou un consistoire et/ou une paroisse');

    $fields = [];


    $fields[] = $this->addFieldInspections($form);
    $fields[] = $this->addFieldConsistoires($form);
    /*
     * $fields[] = $this->addFieldParoisses($form);
     * $fields[] = $this->addFieldRelations($form);
    */

    $form->assign('elements', $fields);
  }

  public function &columns() {
    $columns = [
      'Inspection' => 'inspection',
      'Consistoire' => 'consistoire',
      'Paroisse' => 'paroisse',
      'Nom' => 'nom',
    ];

    foreach ($this->relTypesParoisse as $k => $v) {
      $columns[$v] = "rel_$k";
    }

    $columns['Courriel'] = 'e.email';

    $columns['Complément 1'] = 'a.supplemental_address_1';
    $columns['Complément 2'] = 'a.supplemental_address_2';
    $columns['Rue'] = 'a.street_address';
    $columns['CP'] = 'a.postal_code';
    $columns['Ville'] = 'a.city';

    return $columns;
  }

  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //var_dump($sql);exit;
    return $sql;
  }

  public function select() {
    $selectColumns = " distinct
      inspec.organization_name inspection,
      consist.organization_name consistoire,
      par.organization_name paroisse,
      contact_a.display_name as nom,
      e.email,
      a.*
    ";

    $selectColumns .= $this->getSelectRelationships();

    return $selectColumns;
  }

  public function from() {
    $relationshipFilter = implode(', ',array_keys($this->relTypesParoisse));

    $fromClause = "
      FROM
        civicrm_contact contact_a
      INNER JOIN
        civicrm_relationship r on r.contact_id_a = contact_a.id and r.relationship_type_id in ($relationshipFilter) and r.is_active = 1
      INNER JOIN
        civicrm_contact par on par.id = r.contact_id_b
      INNER JOIN
        civicrm_value_paroisse_detail pard on pard.entity_id = par.id
      LEFT OUTER JOIN
        civicrm_contact inspec on inspec.id = pard.inspection_consistoire_reforme
      LEFT OUTER JOIN
        civicrm_contact consist on consist.id = pard.consistoire_lutherien
      LEFT OUTER JOIN
        civicrm_email e ON e.contact_id = contact_a.id AND e.is_primary = 1
      LEFT OUTER JOIN
        civicrm_address a ON a.contact_id = contact_a.id AND a.is_primary = 1
    ";

    return $fromClause;
  }

  public function where($includeContactIDs = FALSE) {
    $params = [];

    $where = "
        contact_a.is_deleted = 0
      and
        contact_a.contact_type = 'Individual'
    ";

    $where .= $this->getInspectionConsistoireParoisseFilter();

    return $this->whereClause($where, $params);
  }



  private function getInspectionConsistoireParoisseFilter() {
    $where = '';

    $filter = CRM_Utils_Array::value('filter_inspection_consistoire_reforme', $this->_formValues);
    if ($filter) {
      $where = " and pard.inspection_consistoire_reforme = $filter ";
    }

    $filter = CRM_Utils_Array::value('filter_consistoire_lutherien', $this->_formValues);
    if ($filter) {
      $where = " and pard.consistoire_lutherien = $filter ";
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

  private function getNecessaryRelationships() {
    $rels = [];

    $config = new CRM_Uepalconfig_Config();

    // membre elu	président	vice-président	trésorier	secrétaire	receveur	membre invité
    $r = $config->getRelationshipType_estMembreDeDroitDe();
    $rels[$r['id']] = $r['label_a_b'];

    $r = $config->getRelationshipType_estMembreEluDe();
    $rels[$r['id']] = $r['label_a_b'];

    $r = $config->getRelationshipType_estPresidentDe();
    $rels[$r['id']] = $r['label_a_b'];

    $r = $config->getRelationshipType_estVicePresidentDe();
    $rels[$r['id']] = $r['label_a_b'];

    $r = $config->getRelationshipType_estTresorierDe();
    $rels[$r['id']] = $r['label_a_b'];

    $r = $config->getRelationshipType_estSecretaireDe();
    $rels[$r['id']] = $r['label_a_b'];

    $rels[] = 'est Receveur·e de';

    $r = $config->getRelationshipType_estMembreInviteDe();
    $rels[$r['id']] = $r['label_a_b'];

    return $rels;
  }

  private function getSelectRelationships() {
    $col = '';

    foreach ($this->relTypesParoisse as $k => $k) {
      $col .= ", (select if(max(r_$k.id) is null, null, 'x') from civicrm_relationship r_$k where r_$k.relationship_type_id = $k and r_$k.is_active = 1 and r_$k.contact_id_a = contact_a.id and r_$k.contact_id_b = par.id) rel_$k";
    }

    return $col;
  }

  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }



}
