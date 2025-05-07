<?php

namespace Drupal\data_request_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Validator\Constraints\File as SymfonyFile;
class DataRequestForm extends FormBase {

  public function getFormId(): string {
    return 'data_request_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $form['attachment'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload File'),
      '#upload_location' => 'public://testfile/',
      '#required' => TRUE,
      '#constraints' => [
        new SymfonyFile([
          'maxSize' => '25M',
          'mimeTypes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'image/jpeg',
            'image/png',
          ],
          'mimeTypesMessage' => $this->t('Please upload a valid file (pdf, doc, docx, txt, jpg, png).'),
        ]),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $title = $form_state->getValue('title');
    $file_ids = $form_state->getValue('attachment');

    if (!empty($file_ids)) {
      $fid = reset($file_ids);
      $file = File::load($fid);
      dpm($file->getFilename());
      if ($file) {
        $file->setPermanent();
        $file->save();
        \Drupal::messenger()->addStatus($this->t('File "@filename" uploaded successfully with title "@title".', [
          '@filename' => $file->getFilename(),
          '@title' => $title,
        ]));
      }
      else {
        \Drupal::messenger()->addError($this->t('Failed to load the uploaded file.'));
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('No file was uploaded.'));
    }
  }
}
