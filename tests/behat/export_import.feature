@local @local_homepage_config
Feature: Export and import visual configuration
  As a site administrator
  I want to export the visual configuration as a ZIP
  And import it back on another Moodle instance
  So that I can transfer the homepage layout between environments

  Background:
    Given I log in as "admin"

  @javascript
  Scenario: Admin can access the Export / Import page from Site administration
    When I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    Then I should see "Configuration actuelle"
    And I should see "Exporter la configuration"
    And I should see "Importer une configuration"

  @javascript
  Scenario: Export button triggers a ZIP download
    Given I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    When I click on "Télécharger l'export (.zip)" "link"
    # The download is streamed — we just verify no error page is shown.
    Then I should not see "Exception"
    And I should not see "Error"

  @javascript
  Scenario: Import form rejects a non-ZIP file
    Given I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    When I upload "local/homepage_config/tests/fixtures/not_a_zip.txt" file to "Fichier de configuration (.zip)" filemanager
    And I press "Importer"
    Then I should see "Type de fichier invalide"

  @javascript
  Scenario: Import form requires a file before submitting
    Given I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    When I press "Importer"
    Then I should see "Required"

  @javascript
  Scenario: Summary panel reflects settings count after seeding a theme setting
    Given the following config values are set as admin:
      | phpunit_behat_key | phpunit_behat_value | theme_boost_union |
    When I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    Then I should see "paramètres de thème"

  @javascript
  Scenario: Restore blocks checkbox shows the security warning
    Given I navigate to "Local plugins > Configuration page d'accueil > Config. visuelle — Export / Import" in site administration
    Then I should see "Attention : ceci supprimera et remplacera"

  Scenario: Banner hidden container is injected into the front page when bannercfg is set
    Given the following config values are set as admin:
      | bannercfg | [{"html":"<p>Behat banner slide</p>"}] | local_homepage_config |
    When I am on site homepage
    Then "div#hpc-banner-inject" "css_element" should exist
    And I should see "Behat banner slide"

  @javascript
  Scenario: Banner carousel is visible and interactive after AMD initialisation
    Given the following config values are set as admin:
      | bannercfg | [{"html":"<p>Slide A</p>"},{"html":"<p>Slide B</p>"}] | local_homepage_config |
    When I am on site homepage
    Then ".hpc-banner" "css_element" should exist
    And ".hpc-banner__dot" "css_element" should exist
