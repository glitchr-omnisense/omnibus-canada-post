# Changelog

## [Unreleased]

### Fixed
- Post offices carry their opening hours, from each office's Get Post Office Detail (its
  `rel="detail"` link): day 1 Sunday to 7 Saturday read as ISO weekdays, times in pairs, a missing
  closing time read `24:00`. `openingHours` was always empty. An office whose detail fails keeps no
  hours; the search does not fail for it.
- The dead `province` parameter of the post office search (always null) is gone, and the comment
  claiming GetSlip fetches a label later now says the label keeps its link in `Label::$url`.
