# SymfonyExceptions2GitLabIssuesBundle
*That's a very long name, but at least we know what it does.*

Once installed, this bundle will open/update an issue on your GitLab repository
when an exception is thrown:

![SymfonyExceptions2GitLabIssuesBundle](screenshot.png)

- We build the issue title from the exception file, line and message
- If an issue exists with this title, we'll update it
- We put some relevant information in the issue body (request method (GET, POST, ...) & URI, logged in user, stacktrace)
- We add/update a comment: "Thrown 3 times, last one was 14/07/2016 09:37:47"

## Installation
`#TODO`

## TODO
- Make more things configurable:
   - Issue body template
   - Comment datetime format?
- Handle pagination when finding issue
