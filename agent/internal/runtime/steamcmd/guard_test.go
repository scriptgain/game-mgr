package steamcmd

import "testing"

// A Steam Guard prompt has to be recognised, because steamcmd does not treat it
// as an error and will not give up on its own.
//
// Hit live on 2026-08-11: an install against a Guard protected account with no
// sentry sat in nanosleep for four minutes before anyone looked, with a console
// that had simply stopped, and would have held the queue worker for the whole
// six hour timeout. @NoPromptForPassword does not cover this prompt, which is
// the part that is easy to get wrong twice.
func TestGuardPromptIsRecognised(t *testing.T) {
	stalls := []string{
		"Steam Guard code:",
		"Please enter the Steam Guard code from your authenticator app",
		"STEAM GUARD CODE:",
		"Two-factor code:",
		"FAILED (Two-factor code mismatch)",
		"FAILED (Invalid Steam Guard code)",
		"FAILED (Rate Limit Exceeded)",
	}
	for _, line := range stalls {
		if guardPrompt(line) == "" {
			t.Errorf("guardPrompt(%q) said nothing; the install would hang", line)
		}
	}
}

// Ordinary output must not be mistaken for a prompt. A false positive here
// kills a healthy multi gigabyte download partway through, which is a worse
// outcome than the hang it is meant to prevent.
func TestOrdinaryOutputIsNotAPrompt(t *testing.T) {
	fine := []string{
		"Logging in user 'someone' to Steam Public...",
		"Waiting for client config...OK",
		"Update state (0x61) downloading, progress: 41.27 (1234 / 5678)",
		"Success! App '232250' fully installed.",
		"[  0%] Checking for available update...",
		"Redirecting stderr to '/home/gamemgr/Steam/logs/stderr.txt'",
		"ILocalize::AddFile() failed to load file \"public/steambootstrapper_english.txt\".",
		// Close to the real thing on purpose: the word "guard" alone is not a
		// prompt, and a substring match on it would kill this download.
		"Installing breakpad exception handler for appid(steam)",
		"guard page allocated",
	}
	for _, line := range fine {
		if reason := guardPrompt(line); reason != "" {
			t.Errorf("guardPrompt(%q) = %q; a healthy install would be killed", line, reason)
		}
	}
}

// The message has to say what to do, not merely what happened. Somebody reads
// this at the bottom of a stalled console with no other context.
func TestTheMessageSaysWhatToDo(t *testing.T) {
	reason := guardPrompt("Steam Guard code:")
	for _, want := range []string{"shared secret", "authorise this node"} {
		if !contains(reason, want) {
			t.Errorf("the guard message does not mention %q: %s", want, reason)
		}
	}
}

func contains(haystack, needle string) bool {
	return len(haystack) >= len(needle) && (func() bool {
		for i := 0; i+len(needle) <= len(haystack); i++ {
			if haystack[i:i+len(needle)] == needle {
				return true
			}
		}

		return false
	})()
}

// The live prompt can be answered by a person. A mismatch or a rate limit
// cannot: Steam has already counted the attempt, and offering a retry box there
// is how a wrong shared secret becomes a locked account.
func TestOnlyTheLivePromptIsAnswerable(t *testing.T) {
	for _, line := range []string{
		"Steam Guard code:",
		"Please enter the Steam Guard code from your authenticator app",
		"Two-factor code:",
	} {
		if !answerable(line) {
			t.Errorf("answerable(%q) = false; nobody could answer a live prompt", line)
		}
	}

	for _, line := range []string{
		"FAILED (Two-factor code mismatch)",
		"FAILED (Invalid Steam Guard code)",
		"FAILED (Rate Limit Exceeded)",
	} {
		if answerable(line) {
			t.Errorf("answerable(%q) = true; retrying a spent attempt extends the lockout", line)
		}
		if guardPrompt(line) == "" {
			t.Errorf("guardPrompt(%q) said nothing, so the install would hang", line)
		}
	}
}
