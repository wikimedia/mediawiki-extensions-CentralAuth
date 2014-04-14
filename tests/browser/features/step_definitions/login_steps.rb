Given(/^I am logged in to (.+)$/) do |site|
  visit(LoginPage).login_with(ENV["MEDIAWIKI_USER"], ENV["MEDIAWIKI_PASSWORD"])
end

When(/^I visit (.+)$/) do |site|
  @browser.goto site
end

Then(/^I should be logged into (.+) also$/) do |site|
  step "there should be a link to #{ENV['MEDIAWIKI_USER']}"
end
Then(/^there should be a link to (.+)$/) do |text|
  on(LoginPage).username_displayed_element.when_present.text.should == text
end
