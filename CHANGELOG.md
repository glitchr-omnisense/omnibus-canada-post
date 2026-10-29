# Changelog

## [Unreleased]

### Fixed
- A `CarrierException` no longer carries the API username and password. The message quoted the HTTP
  client's (the whole URL), the previous was the client's own exception, and every trace of the
  chain kept the arguments of the calls under it (the request's options, the `Api` with its
  credentials) when `zend.exception_ignore_args` is off: a log of the whole chain wrote them. The
  message masks them as `***`, the previous is a copy of the client's exception (the same class and
  code), and no exception `Api` throws keeps its trace's arguments (`Omnibus\Http\Redaction`).
- Post offices carry their opening hours, from each office's Get Post Office Detail (its
  `rel="detail"` link): day 1 Sunday to 7 Saturday read as ISO weekdays, times in pairs, a missing
  closing time read `24:00`. `openingHours` was always empty. An office whose detail fails keeps no
  hours; the search does not fail for it.
- The dead `province` parameter of the post office search (always null) is gone, and the comment
  claiming GetSlip fetches a label later now says the label keeps its link in `Label::$url`.
