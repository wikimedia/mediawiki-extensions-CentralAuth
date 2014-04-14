Given(/^I am logged in to the primary wiki domain$/) do
  visit(LoginPage).login_with(ENV["MEDIAWIKI_USER"], ENV["MEDIAWIKI_PASSWORD"])
end

When(/^I visit the central login wiki domain$/) do
  @browser.goto ENV["MEDIAWIKI_CENTRALAUTH_LOGINWIKI_URL"]
end

When(/^I visit the alternate wiki domain$/) do
  @browser.goto ENV["MEDIAWIKI_CENTRALAUTH_ALTWIKI_URL"]
end

Then(/^I should be logged into (.+) also$/) do |site|
  step "there should be a link to #{ENV['MEDIAWIKI_USER']}"
end

Then(/^there should be a link to (.+)$/) do |text|
  expect(on(LoginPage).username_displayed_element.when_present.text).to match(text)
end
