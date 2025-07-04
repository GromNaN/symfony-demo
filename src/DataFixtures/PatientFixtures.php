<?php

namespace App\DataFixtures;

use App\Document\Patient;
use App\Document\PatientRecord;
use App\Document\Pathology;
use App\Document\PatientBilling;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;

class PatientFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        for ($i = 1; $i <= 10; $i++) {
            $patientRecord = new PatientRecord(
                sprintf('%09d', random_int(100000000, 999999999)), // random 9-digit SSN
                new PatientBilling(
                    ['insurance', 'private', 'public'][array_rand(['insurance', 'private', 'public'])],
                    strtoupper(bin2hex(random_bytes(4)))
                ),
                random_int(100, 2000)
            );

            $pathologies = new ArrayCollection();
            for ($j = 1; $j <= rand(1, 3); $j++) {
                $pathologies->add(new Pathology(
                    'Pathology-' . $j,
                    new \DateTimeImmutable('-' . random_int(0, 3650) . ' days')
                ));
            }

            $patient = new Patient(
                'Patient ' . $i,
                1000 + $i,
                $patientRecord,
                $pathologies
            );

            $manager->persist($patient);
        }

        $manager->flush();
    }
}
