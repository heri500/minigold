<?php

namespace Drupal\data_product\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to add a new Cabang.
 */
class AddProductForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_cabang_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_cabang = NULL) {
    $cabang_data = [];
    if ($id_cabang) {
      $Database = Database::getConnection();
      $query = $Database->select('z_indogas_datacabang', 'c')
        ->fields('c', ['id', 'kode_cabang', 'nama_cabang', 'nama_cabang_lengkap', 'nama_cabang_singkatan',
          'jam_kerja', 'commision', 'decommision', 'created', 'changed', 'uid', 'keterangan',
          'uid_changed', 'old_id', 'no_urut'])
        ->condition('id', $id_cabang)
        ->execute()
        ->fetchAssoc();

      if ($query) {
        $cabang_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $cabang_data['id'] ?? '',
        ];
      }
    }

    $form['kode'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Kode',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Kode</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $cabang_data['kode_cabang'] ?? '',
    ];

    $form['nama_cabang'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Nama Cabang',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Nama Cabang</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $cabang_data['nama_cabang'] ?? '',
    ];

    $form['keterangan'] = [
      '#type' => 'textarea',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan',
        'cols' => 62,
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
      '#default_value' => $cabang_data['keterangan'] ?? '',
    ];

    $form['nama_lengkap'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Nama Lengkap',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Nama Lengkap</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $cabang_data['nama_cabang_lengkap'] ?? '',
    ];

    $form['nama_singkat'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Nama Singkat',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Nama Singkat</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $cabang_data['nama_cabang_singkatan'] ?? '',
    ];

    $form['jam_kerja'] = [
      '#type' => 'select',
      '#options' => array_combine(range(1, 24), range(1, 24)),
      '#attributes' => [
        'class' => ['form-select'],
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Jam Kerja</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => [$cabang_data['jam_kerja'],$cabang_data['jam_kerja']] ?? [],
    ];

    $form['no_urut'] = [
      '#type' => 'select',
      '#options' => array_combine(range(0, 100), range(0, 100)),
      '#attributes' => [
        'class' => ['form-select'],
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">No. Urut</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => [$cabang_data['no_urut'],$cabang_data['no_urut']] ?? [],
    ];
    if ($cabang_data['commision']) {
      $form['commision'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Commision</span>',
        '#suffix' => '</div>',
        '#default_value' => $cabang_data['commision'] ?? '',
      ];
    }else{
      $form['commision'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Commision</span>',
        '#suffix' => '</div>',
      ];
    }
    if ($cabang_data['decommision']) {
      $form['decommision'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Decommision</span>',
        '#suffix' => '</div>',
        '#default_value' => $cabang_data['decommision'] ?? '',
      ];
    }else{
      $form['decommision'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Decommision</span>',
        '#suffix' => '</div>',
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Simpan'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'markup',
      '#markup' => '&nbsp;<a type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</a>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $fields = [
      'kode_cabang' => $values['kode'],
      'nama_cabang' => $values['nama_cabang'],
      'keterangan' => $values['keterangan'],
      'nama_cabang_lengkap' => $values['nama_lengkap'],
      'nama_cabang_singkatan' => $values['nama_singkat'],
      'jam_kerja' => $values['jam_kerja'],
      'no_urut' => $values['no_urut'],
      'created' => time(),
      'uid' => \Drupal::currentUser()->id(),
    ];
    if (!empty($values['commision'])){
      $fields['commision'] = $values['commision'];
    }
    if (!empty($values['decommision'])){
      $fields['decommision'] = $values['decommision'];
    }
    $connection = Database::getConnection();
    if (!empty($values['id'])){
      $connection->update('z_indogas_datacabang')
        ->fields($fields)
        ->condition('id', $values['id'])
        ->execute();
      $this->messenger()->addMessage($this->t('Update Cabang Berhasil.'));
    }else {
      $connection->insert('z_indogas_datacabang')
        ->fields($fields)
        ->execute();
      $this->messenger()->addMessage($this->t('Tambah Cabang Berhasil.'));
    }
    $form_state->setRedirect('data_cabang.table');
  }

}
