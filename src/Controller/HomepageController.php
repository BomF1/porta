<?php

namespace App\Controller;

use App\Form\UploadFileForm;
use App\Model\StatusCodeEnum;
use App\Model\UserModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    #[Route('/', name: 'upload_file', methods: ['GET', 'POST'])]
    public function uploadFile(Request $request): Response
    {
        $form = $this->createForm(UploadFileForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();

            if ($uploadedFile) {
                try {
                    $fileContent = file_get_contents($uploadedFile->getPathname());
                    $lines = explode("\n", $fileContent);

                    $validUsers = [];
                    $invalidRecords = [];
                    $headerSkipped = false;

                    foreach ($lines as $lineNumber => $line) {
                        $line = trim($line);

                        if (empty($line)) {
                            continue;
                        }

                        $row = str_getcsv($line);

                        if (!$headerSkipped) {
                            $headerSkipped = true;
                            continue;
                        }

                        if (count($row) < 6) {
                            $invalidRecords[] = [
                                'line' => $lineNumber + 1,
                                'data' => $row,
                                'errors' => ['Nedostatok stĺpcov (očakáva sa 6, má ' . count($row) . ')']
                            ];
                            continue;
                        }

                        $uuid = trim($row[0] ?? '');
                        $firstName = trim($row[1] ?? '');
                        $lastName = trim($row[2] ?? '');
                        $email = trim($row[3] ?? '');
                        $password = trim($row[4] ?? '');
                        $statusValue = trim($row[5] ?? '');

                        $validationErrors = $this->validateRowData([
                            'uuid' => $uuid,
                            'firstName' => $firstName,
                            'lastName' => $lastName,
                            'email' => $email,
                            'password' => $password,
                            'status' => $statusValue
                        ]);

                        if (!empty($validationErrors)) {
                            $invalidRecords[] = [
                                'line' => $lineNumber + 1,
                                'data' => [
                                    'uuid' => $uuid,
                                    'firstName' => $firstName,
                                    'lastName' => $lastName,
                                    'email' => $email,
                                    'password' => $password,
                                    'status' => $statusValue
                                ],
                                'errors' => $validationErrors
                            ];
                            continue;
                        }

                        try {
                            $status = StatusCodeEnum::load(intval($statusValue));

                            $user = new UserModel();
                            $user->setUuid($uuid);
                            $user->setFullName($firstName . ' ' . $lastName);
                            $user->setEmail($email);
                            $user->setPassword($password);
                            $user->setStatus($status);

                            $validUsers[] = $user;

                        } catch (\Exception $e) {
                            $invalidRecords[] = [
                                'line' => $lineNumber + 1,
                                'data' => [
                                    'uuid' => $uuid,
                                    'firstName' => $firstName,
                                    'lastName' => $lastName,
                                    'email' => $email,
                                    'password' => $password,
                                    'status' => $statusValue
                                ],
                                'errors' => ['Chyba pri vytváraní objektu: ' . $e->getMessage()]
                            ];
                        }
                    }

                    $projectDir = $this->getParameter('kernel.project_dir');
                    $outputsDir = $projectDir . '/public/outputs';
                    if (!is_dir($outputsDir)) {
                        @mkdir($outputsDir, 0775, true);
                    }

                    $timestamp = date('Ymd-His');
                    $uniqueId = substr(uniqid('', true), -6);

                    if (!empty($validUsers)) {
                        $jsonData = array_map(fn($user) => $user->jsonSerialize(), $validUsers);
                        $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                        $validFilename = "valid-output-$timestamp-$uniqueId.json";
                        $validOutputPath = $outputsDir . '/' . $validFilename;
                        file_put_contents($validOutputPath, $jsonContent);
                    }

                    if (!empty($invalidRecords)) {
                        $invalidContent = json_encode($invalidRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                        $invalidFilename = "invalid-output-$timestamp-$uniqueId.json";
                        $invalidOutputPath = $outputsDir . '/' . $invalidFilename;
                        file_put_contents($invalidOutputPath, $invalidContent);
                    }

                    $validCount = count($validUsers);
                    $invalidCount = count($invalidRecords);

                    if ($validCount > 0) {
                        $this->addFlash('success',
                            "Úspešne spracovaných $validCount validných používateľov. " .
                            "Uložené do súboru: valid-users-$timestamp-$uniqueId.json"
                        );
                    }

                    if ($invalidCount > 0) {
                        $this->addFlash('warning',
                            "Nájdených $invalidCount nevalidných záznamov. " .
                            "Uložené do súboru: invalid-records-$timestamp-$uniqueId.json"
                        );
                    }

                    if ($validCount === 0 && $invalidCount === 0) {
                        $this->addFlash('info', 'Neboli nájdené žiadne dáta na spracovanie.');
                    }

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Chyba pri spracovaní súboru: ' . $e->getMessage());
                }

            } else {
                $this->addFlash('error', 'Prosím, vyberte súbor na nahratie.');
            }

            return $this->redirectToRoute('upload_file');
        }

        return $this->render('home/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/view-output', name: 'view_output', methods: ['GET'])]
    public function viewOutput(): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $outputsDir = $projectDir . '/public/outputs';

        $files = [];
        if (is_dir($outputsDir)) {
            $dirFiles = scandir($outputsDir);
            foreach ($dirFiles as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (!str_ends_with($file, '.json')) {
                    continue;
                }

                $fileInfo = [
                    'name' => $file,
                    'type' => str_starts_with($file, 'valid-') ? 'valid' : 'invalid',
                    'size' => filesize($outputsDir . '/' . $file),
                    'modified' => filemtime($outputsDir . '/' . $file)
                ];

                $files[] = $fileInfo;
            }

            usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        }

        return $this->render('home/view_output.html.twig', [
            'files' => $files,
        ]);
    }



    private function validateRowData(array $data): array
    {
        $errors = [];
        if (empty($data['uuid'])) {
            $errors[] = 'UUID je povinné';
        } elseif (!$this->isValidUuid($data['uuid'])) {
            $errors[] = 'UUID má neplatný formát';
        }

        if (empty($data['firstName'])) {
            $errors[] = 'Krstné meno je povinné';
        } elseif (strlen($data['firstName']) < 2) {
            $errors[] = 'Krstné meno musí mať aspoň 2 znaky';
        } elseif (strlen($data['firstName']) > 50) {
            $errors[] = 'Krstné meno nesmie prekročiť 50 znakov';
        } elseif (!preg_match('/^[a-zA-ZáčďéíľĺňóôŕšťúýžÁČĎÉÍĽĹŇÓÔŔŠŤÚÝŽ\s\-]+$/u', $data['firstName'])) {
            $errors[] = 'Krstné meno obsahuje nepovolené znaky';
        }

        if (empty($data['lastName'])) {
            $errors[] = 'Priezvisko je povinné';
        } elseif (strlen($data['lastName']) < 2) {
            $errors[] = 'Priezvisko musí mať aspoň 2 znaky';
        } elseif (strlen($data['lastName']) > 50) {
            $errors[] = 'Priezvisko nesmie prekročiť 50 znakov';
        } elseif (!preg_match('/^[a-zA-ZáčďéíľĺňóôŕšťúýžÁČĎÉÍĽĹŇÓÔŔŠŤÚÝŽ\s\-]+$/u', $data['lastName'])) {
            $errors[] = 'Priezvisko obsahuje nepovolené znaky';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email je povinný';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email má neplatný formát';
        } elseif (strlen($data['email']) > 255) {
            $errors[] = 'Email nesmie prekročiť 255 znakov';
        }

        if (empty($data['password'])) {
            $errors[] = 'Heslo je povinné';
        } elseif (strlen($data['password']) === 0) {
            $errors[] = 'Heslo je povinné';
        } elseif (strlen($data['password']) > 255) {
            $errors[] = 'Heslo nesmie prekročiť 255 znakov';
        }

        if (empty($data['status']) && $data['status'] !== '2' && $data['status'] !== '1') {
            $errors[] = 'Status je povinný';
        } elseif (!in_array($data['status'], ['2', '1'], true)) {
            $errors[] = 'Status musí byť 2 (neaktívny) alebo 1 (aktívny)';
        }

        return $errors;
    }

    private function isValidUuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }
}
