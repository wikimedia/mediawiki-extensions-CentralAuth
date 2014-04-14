Before do
  def expect_env(name)
    msg = "You must define #{name} in your environment before running CentralAuth browser tests"
    expect(ENV[name]).to_not be_nil, msg
    expect(ENV[name]).to_not be_empty, msg
  end

  expect_env("MEDIAWIKI_CENTRALAUTH_LOGINWIKI_URL")
  expect_env("MEDIAWIKI_CENTRALAUTH_ALTWIKI_URL")
end
