@chrome @en.wikipedia.beta.wmflabs.org @firefox @vagrant
Feature: Global Account Manage

  Background:
    Given I am at Special:CentralAuth

  Scenario: Go to special page
    Then target element should be there

  Scenario: Lookup invalid user
    When I lookup an invalid user
    Then error box should be visible

  Scenario: Lookup a valid user
    When I lookup a valid user
    Then global account information should be visible
