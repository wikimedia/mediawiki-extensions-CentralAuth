Given(/^I am using a global account$/) do
  expect(api.meta('globaluserinfo').data).to_not(
      include('missing'),
      'the current acccount is not global'
  )
end

Given(/^I am logged in to the primary wiki domain$/) do
  log_in
end

When(/^I visit the central login wiki domain$/) do
  visit_wiki(:login)
end

When(/^I visit the alternate wiki domain$/) do
  visit_wiki(:alternative)
end

Then(/^I should be logged in$/) do
  expect(visit(LoginPage).username_displayed_element.when_present.text).to match(user_label)
end
