/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package oracle

import (
	"context"
	"fmt"
	"strconv"
)

const keySessions = "oracle.sessions.stats"

const sessionsMaxParams = 1

// sessionsHandler TODO: add description.
func sessionsHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var (
		sessions string
		err      error
	)

	lockMaxTime := 600

	if len(params) > sessionsMaxParams {
		return nil, errorTooManyParameters
	}

	if len(params) == 1 {
		lockMaxTime, err = strconv.Atoi(params[0])
		if err != nil {
			return nil, errorInvalidParams
		}
	}

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECTAGG(v.METRIC VALUE v.VALUE)
		FROM
			(
			SELECT
				METRIC, SUM(VALUE) AS VALUE
			FROM
				(
				SELECT
					LOWER(REPLACE(STATUS || ' ' || TYPE, ' ', '_')) AS METRIC, 
					COUNT(*) AS VALUE
				FROM
					V$SESSION
				GROUP BY
					STATUS, TYPE
					
				UNION
				
				SELECT
					DISTINCT *
				FROM
					TABLE(sys.ODCIVARCHAR2LIST('inactive_user', 'active_user', 'active_background')), 
					TABLE(sys.ODCINUMBERLIST(0, 0, 0))
				)
			GROUP BY
				METRIC
				
			UNION
			
			SELECT
				'total' AS METRIC, 
				COUNT(*) AS VALUE
			FROM
				V$SESSION 
				
			UNION
			
			SELECT
				'long_time_locked' AS METRIC, 
				COUNT(*) AS VALUE
			FROM
				V$SESSION
			WHERE
				BLOCKING_SESSION IS NOT NULL
				AND BLOCKING_SESSION_STATUS = 'VALID'
				AND SECONDS_IN_WAIT > :1

			UNION
			
			SELECT
				'lock_rate' ,
				(cnt_block / cnt_all)* 100 pct
			FROM
				(
				SELECT
					COUNT(*) cnt_block
				FROM
					v$session
				WHERE
					blocking_session IS NOT NULL),
				(
				SELECT
					COUNT(*) cnt_all
				FROM
					gv$session)
			UNION
			SELECT
				'concurrency_rate',
				NVL(ROUND(SUM(duty_act.CNT * 100 / num_cores.val)), 0)
			FROM
				(
					SELECT
						DECODE(SESSION_STATE, 'ON CPU', 'CPU', WAIT_CLASS) WAIT_CLASS, ROUND(COUNT(*) / (60 * 15), 1) CNT
					FROM
						V$ACTIVE_SESSION_HISTORY sh
					WHERE
						sh.SAMPLE_TIME >= SYSDATE - 15 / 1440
						AND DECODE(SESSION_STATE, 'ON CPU', 'CPU', WAIT_CLASS) IN ('Concurrency')
					GROUP BY
						DECODE(SESSION_STATE, 'ON CPU', 'CPU', WAIT_CLASS)
				) duty_act,
				(
					SELECT
						SUM(VALUE) VAL
					FROM
						V$OSSTAT
					WHERE
						STAT_NAME = 'NUM_CPU_CORES'
				) num_cores
			) v
	`, lockMaxTime)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	err = row.Scan(&sessions)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotParseData, err.Error())
	}

	return sessions, nil
}
