<?php

// Default scales/operationalRiskScales/method/thresholds/soaScaleComments used
// when the knowledge base source (MOSP) doesn't provide them. Extracted once
// from tests/fixtures/templates/monarc.json — never read from the fixture at
// runtime. See MonarcExportService / MospToMonarcConverter.
return [
    'scales' => [
        1 => [
            'min' => 0,
            'max' => 4,
            'type' => 1,
            'scaleImpactTypes' => [
                0 => [
                    'id' => 17,
                    'type' => 1,
                    'label' => 'Confidentialité',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Impact inexistant. 
Le critère de confidentialité n’est pas important',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Impact faible, négligeable
La divulgation est défavorable aux intérêts de l’organisation
Exemples : 
- Divulgation d’information interne ne devant pas sortir de l’organisme
- Note de service
- Annuaire téléphonique interne',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Impact moyen, acceptable 
La divulgation nuit aux intérêts de l’organisation
Exemples :
- Divulgation d’information moyennement sensible restreinte à un groupe de personnes
- schéma de réseau interne
- Documentation ou programme source non critique',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Impact sérieux, difficilement supportable 
La divulgation nuit gravement aux intérêts de l’organisation
Exemples :
- Divulgation d’information confidentielle
- Secret bancaire
- Données à caractère personnelles sensibles
- Incidents de sécurité',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Impact très sérieux, insupportable
La divulgation nuit de façon quasi insurmontable aux intérêts de l’organisation.
Exemples :
- Divulgation d’information secrète ou très sensible
- Information classifiée par la loi (EU, OTAN, nationale, etc.)',
                        ],
                    ],
                ],
                1 => [
                    'id' => 18,
                    'type' => 2,
                    'label' => 'Intégrité',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Impact inexistant. 
Le critère d’intégrité n’est pas important.',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Impact faible, négligeable
Corruption facile à rectifier et sans grandes conséquences.
Exemples :
- Courrier interne, e-mail interne',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Impact moyen, acceptable 
Corruption occasionnant une gêne modérée aux parties prenantes. Le rétablissement est facile.
Exemples :
- Site Internet informationnel',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Impact très sérieux, insupportable
Corruption non recouvrable débouchant sur une indisponibilité définitive',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Impact très sérieux, insupportable
Corruption non recouvrable débouchant sur une indisponibilité définitive',
                        ],
                    ],
                ],
                2 => [
                    'id' => 19,
                    'type' => 3,
                    'label' => 'Disponibilité',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Impact inexistant. 
Le critère de disponibilité n’est pas important',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Impact faible, négligeable
Indisponibilité gênante, mais pas encore préjudiciable pour les parties prenantes',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Impact moyen, acceptable 
Indisponibilité occasionnant une gêne modérée aux parties prenantes.
Exemples :
- Délais maximums considérés comme insupportables pas encore atteints',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Impact sérieux, difficilement supportable 
Indisponibilité occasionnant une gêne considérable aux parties prenantes.
Exemples :
- Atteinte des délais maximums considérés comme insupportables',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Impact très sérieux, insupportable
Indisponibilité demandant un très gros effort de rétablissement, voire définitif.
Exemples :
- Large dépassement des délais maximums considérés comme insupportables',
                        ],
                    ],
                ],
                3 => [
                    'id' => 20,
                    'type' => 4,
                    'label' => 'Réputation',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Critique ponctuelle dans les médias.',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Altération passagère de l’image de l’organisme ou de ses représentants.
Critique occasionnelle dans les médias.',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Très forte altération de l’image de l’organisme ou de ses représentants.
Critique sérieuse et répétée dans les médias.',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Mort d’homme. 
Altération définitive de l’image de l’organisme ou de ses représentants.
Couverture médiatique internationale.',
                        ],
                    ],
                ],
                4 => [
                    'id' => 21,
                    'type' => 5,
                    'label' => 'Opérationnel',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Incident mineur sans impact sur la clientèle',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Incident isolé avec impact gérable sur la clientèle',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Interruption d\'un service entier',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Arrêt total de tous les services',
                        ],
                    ],
                ],
                5 => [
                    'id' => 22,
                    'type' => 6,
                    'label' => 'Légal',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Très peu de chance qu’il y est condamnation, ou elle sera très légère. 
Toute poursuite serait vaine.',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Condamnation possible de l’organisme',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Condamnation de l’organisme',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Condamnation lourde de l\'organisme.',
                        ],
                    ],
                ],
                6 => [
                    'id' => 23,
                    'type' => 7,
                    'label' => 'Financier',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Engage quelques frais négligeables. (+/- 1% CA)',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Engage des frais non négligeables. (+/- 5% CA)',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Engage des frais considérables qui auront de répercussions sur l’organisme. (+/- 10% CA)',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Engage de frais quasiment insurmontable. (+/- 20% CA)',
                        ],
                    ],
                ],
                7 => [
                    'id' => 24,
                    'type' => 8,
                    'label' => 'Personne',
                    'isSys' => true,
                    'isHidden' => false,
                    'scaleComments' => [
                        0 => [
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'comment' => 'Quelques désagréments qui seront surmontés sans difficulté (perte de temps, réitérations de démarches, agacement, énervement, etc.)',
                        ],
                        2 => [
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'comment' => 'Désagréments significatifs qui pourront être surmontés malgré quelques difficultés (frais supplémentaires, refus d’accès à des prestations commerciales, peur, incompréhension, stress, affection physique mineure, etc.)',
                        ],
                        3 => [
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'comment' => 'Conséquences significatives qui devraient être surmontées, mais avec de sérieuses difficultés (détournement d’argent, interdiction bancaire, dégradation de bien, perte d’emploi, assignation en justice, aggravation de santé, etc.)',
                        ],
                        4 => [
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'comment' => 'Conséquences significatives, voire irrémédiables, qui pourraient ne pas être surmontées (péril financier, dettes importantes, impossibilité de travailler, affection psychologique ou physique de longue durée, décès, etc.)',
                        ],
                    ],
                ],
            ],
            'scaleComments' => [
            ],
        ],
        2 => [
            'min' => 0,
            'max' => 4,
            'type' => 2,
            'scaleImpactTypes' => [
            ],
            'scaleComments' => [
                0 => [
                    'scaleIndex' => 0,
                    'scaleValue' => 0,
                    'comment' => 'Impossible',
                ],
                1 => [
                    'scaleIndex' => 1,
                    'scaleValue' => 1,
                    'comment' => 'Très improbable : jamais arrivé, nécessite d\'un haut niveau d\'expertise, ou très coûteux à mettre en œuvre.',
                ],
                2 => [
                    'scaleIndex' => 2,
                    'scaleValue' => 2,
                    'comment' => 'Improbable : Peut être déjà survenu, phénomène rare ou nécessite d\'un bon niveau d\'expertise ou coûteux à mettre en œuvre.',
                ],
                3 => [
                    'scaleIndex' => 3,
                    'scaleValue' => 3,
                    'comment' => 'Peut arriver de temps à autre.',
                ],
                4 => [
                    'scaleIndex' => 4,
                    'scaleValue' => 4,
                    'comment' => 'Très probable facile à mettre en œuvre, pas d\'investissement ou d\'expertise particulière.',
                ],
            ],
        ],
        3 => [
            'min' => 0,
            'max' => 5,
            'type' => 3,
            'scaleImpactTypes' => [
            ],
            'scaleComments' => [
                0 => [
                    'scaleIndex' => 0,
                    'scaleValue' => 0,
                    'comment' => 'Pas de vulnérabilité',
                ],
                1 => [
                    'scaleIndex' => 1,
                    'scaleValue' => 1,
                    'comment' => 'Vulnérabilité très faible : Des mesures efficaces sont en place, leurs efficacités sont contrôlées.
Très bonne maturité : Les bonnes pratiques sont implémentées et périodiquement vérifiées.',
                ],
                2 => [
                    'scaleIndex' => 2,
                    'scaleValue' => 2,
                    'comment' => 'Vulnérabilité faible : Des mesures efficaces sont en place.
Bonne maturité : Les bonnes pratiques sont implémentées.',
                ],
                3 => [
                    'scaleIndex' => 3,
                    'scaleValue' => 3,
                    'comment' => 'Vulnérabilité normale : Des mesures sont en place, elle peuvent encore être améliorées.
Maturité moyenne : Les bonnes pratiques sont implémentées sans recherche d\'amélioration.',
                ],
                4 => [
                    'scaleIndex' => 4,
                    'scaleValue' => 4,
                    'comment' => 'Vulnérabilité élevée : Des mesures sont en place, mais elles sont peu efficaces ou inadaptées.
Maturité faible : Les bonnes pratiques ne sont pas implémentées, pratiques primaires sans réflexion.',
                ],
                5 => [
                    'scaleIndex' => 5,
                    'scaleValue' => 5,
                    'comment' => 'Vulnérabilité très élevée : aucune mesure en place.
Maturité très faible - Aucune maturité.',
                ],
            ],
        ],
    ],
    'operationalRiskScales' => [
        1 => [
            'min' => 0,
            'max' => 4,
            'type' => 1,
            'operationalRiskScaleTypes' => [
                0 => [
                    'id' => 11,
                    'label' => 'Réputation',
                    'isHidden' => false,
                    'operationalRiskScaleComments' => [
                        0 => [
                            'id' => 61,
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'isHidden' => false,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'id' => 62,
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'isHidden' => false,
                            'comment' => 'Critique ponctuelle dans les médias.',
                        ],
                        2 => [
                            'id' => 63,
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'isHidden' => false,
                            'comment' => 'Altération passagère de l’image de l’organisme ou de ses représentants.
Critique occasionnelle dans les médias.',
                        ],
                        3 => [
                            'id' => 64,
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'isHidden' => false,
                            'comment' => 'Très forte altération de l’image de l’organisme ou de ses représentants.
Critique sérieuse et répétée dans les médias.',
                        ],
                        4 => [
                            'id' => 65,
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'isHidden' => false,
                            'comment' => 'Mort d’homme. 
Altération définitive de l’image de l’organisme ou de ses représentants.
Couverture médiatique internationale.',
                        ],
                    ],
                ],
                1 => [
                    'id' => 12,
                    'label' => 'Opérationnel',
                    'isHidden' => false,
                    'operationalRiskScaleComments' => [
                        0 => [
                            'id' => 66,
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'isHidden' => false,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'id' => 67,
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'isHidden' => false,
                            'comment' => 'Incident mineur sans impact sur la clientèle',
                        ],
                        2 => [
                            'id' => 68,
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'isHidden' => false,
                            'comment' => 'Incident isolé avec impact gérable sur la clientèle',
                        ],
                        3 => [
                            'id' => 69,
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'isHidden' => false,
                            'comment' => 'Interruption d\'un service entier',
                        ],
                        4 => [
                            'id' => 70,
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'isHidden' => false,
                            'comment' => 'Arrêt total de tous les services',
                        ],
                    ],
                ],
                2 => [
                    'id' => 13,
                    'label' => 'Légal',
                    'isHidden' => false,
                    'operationalRiskScaleComments' => [
                        0 => [
                            'id' => 71,
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'isHidden' => false,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'id' => 72,
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'isHidden' => false,
                            'comment' => 'Très peu de chance qu’il y est condamnation, ou elle sera très légère. 
Toute poursuite serait vaine.',
                        ],
                        2 => [
                            'id' => 73,
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'isHidden' => false,
                            'comment' => 'Condamnation possible de l’organisme',
                        ],
                        3 => [
                            'id' => 74,
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'isHidden' => false,
                            'comment' => 'Condamnation de l’organisme',
                        ],
                        4 => [
                            'id' => 75,
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'isHidden' => false,
                            'comment' => 'Condamnation lourde de l\'organisme.',
                        ],
                    ],
                ],
                3 => [
                    'id' => 14,
                    'label' => 'Financier',
                    'isHidden' => false,
                    'operationalRiskScaleComments' => [
                        0 => [
                            'id' => 76,
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'isHidden' => false,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'id' => 77,
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'isHidden' => false,
                            'comment' => 'Engage quelques frais négligeables. (+/- 1% CA)',
                        ],
                        2 => [
                            'id' => 78,
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'isHidden' => false,
                            'comment' => 'Engage des frais non négligeables. (+/- 5% CA)',
                        ],
                        3 => [
                            'id' => 79,
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'isHidden' => false,
                            'comment' => 'Engage des frais considérables qui auront de répercussions sur l’organisme. (+/- 10% CA)',
                        ],
                        4 => [
                            'id' => 80,
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'isHidden' => false,
                            'comment' => 'Engage de frais quasiment insurmontable. (+/- 20% CA)',
                        ],
                    ],
                ],
                4 => [
                    'id' => 15,
                    'label' => 'Personne',
                    'isHidden' => false,
                    'operationalRiskScaleComments' => [
                        0 => [
                            'id' => 81,
                            'scaleIndex' => 0,
                            'scaleValue' => 0,
                            'isHidden' => false,
                            'comment' => 'Pas d\'impact',
                        ],
                        1 => [
                            'id' => 82,
                            'scaleIndex' => 1,
                            'scaleValue' => 1,
                            'isHidden' => false,
                            'comment' => 'Quelques désagréments qui seront surmontés sans difficulté (perte de temps, réitérations de démarches, agacement, énervement, etc.)',
                        ],
                        2 => [
                            'id' => 83,
                            'scaleIndex' => 2,
                            'scaleValue' => 2,
                            'isHidden' => false,
                            'comment' => 'Désagréments significatifs qui pourront être surmontés malgré quelques difficultés (frais supplémentaires, refus d’accès à des prestations commerciales, peur, incompréhension, stress, affection physique mineure, etc.)',
                        ],
                        3 => [
                            'id' => 84,
                            'scaleIndex' => 3,
                            'scaleValue' => 3,
                            'isHidden' => false,
                            'comment' => 'Conséquences significatives qui devraient être surmontées, mais avec de sérieuses difficultés (détournement d’argent, interdiction bancaire, dégradation de bien, perte d’emploi, assignation en justice, aggravation de santé, etc.)',
                        ],
                        4 => [
                            'id' => 85,
                            'scaleIndex' => 4,
                            'scaleValue' => 4,
                            'isHidden' => false,
                            'comment' => 'Conséquences significatives, voire irrémédiables, qui pourraient ne pas être surmontées (péril financier, dettes importantes, impossibilité de travailler, affection psychologique ou physique de longue durée, décès, etc.)',
                        ],
                    ],
                ],
            ],
            'operationalRiskScaleComments' => [
            ],
        ],
        2 => [
            'min' => 0,
            'max' => 4,
            'type' => 2,
            'operationalRiskScaleTypes' => [
            ],
            'operationalRiskScaleComments' => [
                0 => [
                    'id' => 86,
                    'scaleIndex' => 0,
                    'scaleValue' => 0,
                    'isHidden' => false,
                    'comment' => 'Impossible',
                ],
                1 => [
                    'id' => 87,
                    'scaleIndex' => 1,
                    'scaleValue' => 1,
                    'isHidden' => false,
                    'comment' => 'Très improbable : jamais arrivé, nécessite d\'un haut niveau d\'expertise, ou très coûteux à mettre en œuvre.',
                ],
                2 => [
                    'id' => 88,
                    'scaleIndex' => 2,
                    'scaleValue' => 2,
                    'isHidden' => false,
                    'comment' => 'Improbable : Peut être déjà survenu, phénomène rare ou nécessite d\'un bon niveau d\'expertise ou coûteux à mettre en œuvre.',
                ],
                3 => [
                    'id' => 89,
                    'scaleIndex' => 3,
                    'scaleValue' => 3,
                    'isHidden' => false,
                    'comment' => 'Peut arriver de temps à autre.',
                ],
                4 => [
                    'id' => 90,
                    'scaleIndex' => 4,
                    'scaleValue' => 4,
                    'isHidden' => false,
                    'comment' => 'Très probable facile à mettre en œuvre, pas d\'investissement ou d\'expertise particulière.',
                ],
            ],
        ],
    ],
    'method' => [
        'steps' => [
            'initAnrContext' => 0,
            'initEvalContext' => 0,
            'initRiskContext' => 0,
            'initDefContext' => 0,
            'modelImpacts' => 0,
            'modelSummary' => 0,
            'evalRisks' => 1,
            'evalPlanRisks' => 1,
            'manageRisks' => 0,
        ],
        'data' => [
            'contextAnaRisk' => '',
            'contextGestRisk' => '',
            'synthThreat' => '',
            'synthAct' => '',
        ],
        'deliveries' => [
        ],
        'questions' => [
            1 => [
                'id' => 27,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quelle est la raison d\'être de votre structure ?',
                'response' => null,
                'type' => 1,
                'position' => 1,
                'questionChoices' => [
                ],
            ],
            2 => [
                'id' => 28,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quelle est l\'évolution de votre activité de dernières années ?',
                'response' => null,
                'type' => 1,
                'position' => 2,
                'questionChoices' => [
                ],
            ],
            3 => [
                'id' => 29,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quelle est l\'évolution du contexte externe (concurrence, évolution marché, lois, etc.) ?',
                'response' => null,
                'type' => 1,
                'position' => 3,
                'questionChoices' => [
                ],
            ],
            4 => [
                'id' => 30,
                'mode' => 0,
                'isMultiChoice' => true,
                'label' => 'Quels pourraient être les motifs d\'attaque contre votre structure ?',
                'response' => null,
                'type' => 2,
                'position' => 4,
                'questionChoices' => [
                    0 => [
                        'id' => 15,
                        'label' => 'Argent',
                        'position' => 1,
                    ],
                    1 => [
                        'id' => 16,
                        'label' => 'Avantage économique ou commercial',
                        'position' => 2,
                    ],
                    2 => [
                        'id' => 17,
                        'label' => 'Revanche (ex-interne, externe)',
                        'position' => 3,
                    ],
                    3 => [
                        'id' => 18,
                        'label' => 'Avantage politique',
                        'position' => 4,
                    ],
                    4 => [
                        'id' => 19,
                        'label' => 'Satisfaction personnelle',
                        'position' => 5,
                    ],
                    5 => [
                        'id' => 20,
                        'label' => 'Couverture médiatique',
                        'position' => 6,
                    ],
                    6 => [
                        'id' => 21,
                        'label' => 'Espionnage économique',
                        'position' => 7,
                    ],
                ],
            ],
            5 => [
                'id' => 31,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quels sont vos processus métiers les plus importants ?',
                'response' => null,
                'type' => 1,
                'position' => 5,
                'questionChoices' => [
                ],
            ],
            6 => [
                'id' => 32,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quel est l\'actif ayant le plus de valeur dans votre structure ?',
                'response' => null,
                'type' => 1,
                'position' => 6,
                'questionChoices' => [
                ],
            ],
            7 => [
                'id' => 33,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Pour votre activité et vos données quel est le critère le plus important ?',
                'response' => null,
                'type' => 1,
                'position' => 7,
                'questionChoices' => [
                ],
            ],
            8 => [
                'id' => 34,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Votre activité est-elle contrainte au respect de lois, normes, règlements spécifiques (par ex. santé/médical - loi relative à la protection des informations sur le patient) ?',
                'response' => null,
                'type' => 1,
                'position' => 8,
                'questionChoices' => [
                ],
            ],
            9 => [
                'id' => 35,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quelle est la menace la plus importante pour votre activité ?',
                'response' => null,
                'type' => 1,
                'position' => 9,
                'questionChoices' => [
                ],
            ],
            10 => [
                'id' => 36,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Quelles sont vos plus grandes craintes (ce qui pourrait anéantir votre activité)?',
                'response' => null,
                'type' => 1,
                'position' => 10,
                'questionChoices' => [
                ],
            ],
            11 => [
                'id' => 37,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Avez-vous déjà connu un sinistre ou une attaque informatique ?',
                'response' => null,
                'type' => 1,
                'position' => 11,
                'questionChoices' => [
                ],
            ],
            12 => [
                'id' => 38,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Vos concurrents ont-ils déjà connu un sinistre ou attaque informatique ?',
                'response' => null,
                'type' => 1,
                'position' => 12,
                'questionChoices' => [
                ],
            ],
            13 => [
                'id' => 39,
                'mode' => 0,
                'isMultiChoice' => false,
                'label' => 'Votre environnement géographique vous prédispose-t-il à un risque de type (inondation, feu, tempête, fortes neiges...)',
                'response' => null,
                'type' => 1,
                'position' => 13,
                'questionChoices' => [
                ],
            ],
        ],
        'threats' => [
        ],
    ],
    'thresholds' => [
        'seuil1' => 8,
        'seuil2' => 27,
        'seuilRolf1' => 2,
        'seuilRolf2' => 6,
    ],
    'soaScaleComments' => [
        0 => [
            'scaleIndex' => 0,
            'isHidden' => false,
            'colour' => '#FFFFFF',
            'comment' => 'Inexistant',
        ],
        1 => [
            'scaleIndex' => 1,
            'isHidden' => false,
            'colour' => '#FD661F',
            'comment' => 'Initialisé',
        ],
        2 => [
            'scaleIndex' => 2,
            'isHidden' => false,
            'colour' => '#FD661F',
            'comment' => 'Reproductible',
        ],
        3 => [
            'scaleIndex' => 3,
            'isHidden' => false,
            'colour' => '#FFBC1C',
            'comment' => 'Défini',
        ],
        4 => [
            'scaleIndex' => 4,
            'isHidden' => false,
            'colour' => '#FFBC1C',
            'comment' => 'Géré quantitativement',
        ],
        5 => [
            'scaleIndex' => 5,
            'isHidden' => false,
            'colour' => '#D6F107',
            'comment' => 'Optimisé',
        ],
    ],
];
