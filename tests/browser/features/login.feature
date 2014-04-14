@chrome @firefox @login @phantomjs @test2.wikipedia.org
Feature: CentralAuth log in

  Scenario: Test central-domain login
    Given I am logged in to test2.wikipedia.org
    When I visit login.wikimedia.org
    Then I should be logged into login.wikimedia.org also

  Scenario: Test cross-domain login
    Given I am logged in to test2.wikipedia.org
    When I visit test.wikidata.org
    Then I should be logged into test.wikidata.org also
